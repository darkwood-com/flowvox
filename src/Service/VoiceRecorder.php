<?php

declare(strict_types=1);

namespace App\Service;

use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * macOS voice recorder using ffmpeg (avfoundation). Records to a WAV file.
 *
 * No chunk streaming; start() spawns ffmpeg writing to a file, stop() stops it
 * gracefully (SIGINT to finalize WAV header). Output: mono 16 kHz, PCM 16-bit LE,
 * suitable for whisper.cpp.
 *
 * Requires: ffmpeg with avfoundation (macOS). On first run, grant microphone
 * permission when prompted.
 */
final class VoiceRecorder
{
    private const DEFAULT_AVFOUNDATION_AUDIO_DEVICE = 2;
    private const SAMPLE_RATE = 16000;
    private const CHANNELS = 1;
    private const GRACEFUL_WAIT_SECONDS = 3;

    private ?Process $process = null;
    private bool $recording = false;
    private ?string $currentFilePath = null;
    private ?\DateTimeImmutable $startedAt = null;

    /** Set when requestStop() has been called (SIGINT sent). */
    private bool $stopRequested = false;
    /** Deadline (timestamp) after which we may send SIGTERM. */
    private ?float $stopDeadline = null;
    /** Set after SIGTERM has been sent (non-blocking stop flow). */
    private bool $sigtermSent = false;

    public function __construct(
        private readonly string $projectDir,
        private readonly ?string $ffmpegPath = null,
        private readonly int $avfoundationAudioDevice = self::DEFAULT_AVFOUNDATION_AUDIO_DEVICE,
    ) {
    }

    /**
     * Start recording. Spawns ffmpeg writing to a WAV file.
     *
     * @param string|null $outputPath If null, creates var/voice/<timestamp>-<random>.wav
     * @return string The path where the WAV file is (or will be) written
     * @throws RuntimeException if already recording, ffmpeg not found, or spawn fails
     */
    public function start(?string $outputPath = null): string
    {
        if ($this->recording) {
            throw new RuntimeException('VoiceRecorder is already recording.');
        }

        if (!self::isFfmpegAvailable($this->ffmpegPath)) {
            throw new RuntimeException(
                'ffmpeg is not available. Install it (e.g. brew install ffmpeg) and ensure it is in PATH.'
            );
        }

        $path = $outputPath ?? $this->createDefaultOutputPath();
        $dir = dirname($path);
        if (!is_dir($dir)) {
            if (!@mkdir($dir, 0755, true)) {
                throw new RuntimeException(sprintf('Cannot create voice output directory: %s', $dir));
            }
        }

        $command = $this->buildFfmpegCommand($path);
        $this->process = new Process($command, null, null, null, null);
        $this->process->setTimeout(null);
        $this->process->start();

        if (!$this->process->isRunning()) {
            $err = $this->process->getErrorOutput() ?: 'process exited immediately';
            $this->process = null;
            throw new RuntimeException(sprintf('Failed to start ffmpeg: %s', $err));
        }

        $this->recording = true;
        $this->currentFilePath = $path;
        $this->startedAt = new \DateTimeImmutable();

        return $path;
    }

    /**
     * Request stop without blocking. Sends SIGINT once; POOL will call pollStop() until finalized.
     */
    public function requestStop(): void
    {
        if (!$this->recording || $this->stopRequested) {
            return;
        }
        $proc = $this->process;
        if ($proc !== null && $proc->isRunning()) {
            $proc->signal(\SIGINT);
            $this->stopRequested = true;
            $this->stopDeadline = microtime(true) + self::GRACEFUL_WAIT_SECONDS;
        }
    }

    /**
     * Non-blocking poll: check if stop has finalized. Call repeatedly from POOL.
     *
     * @return string|null Path to the finalized WAV file when stop is complete, null otherwise
     * @throws RuntimeException if the process exited with an error
     */
    public function pollStop(): ?string
    {
        if (!$this->recording) {
            return null;
        }

        $proc = $this->process;
        $path = $this->currentFilePath;

        if ($proc === null) {
            $this->recording = false;
            $this->resetStopState();

            return $path;
        }

        if (!$proc->isRunning()) {
            $exitCode = $proc->getExitCode();
            $weRequestedStop = $this->stopRequested;
            $this->process = null;
            $this->recording = false;
            $this->resetStopState();
            if ($exitCode !== null && $exitCode !== 0 && !$weRequestedStop) {
                throw new RuntimeException(sprintf(
                    'ffmpeg exited unexpectedly with code %d: %s',
                    $exitCode,
                    $proc->getErrorOutput() ?: 'no stderr'
                ));
            }

            return $path;
        }

        if ($this->stopRequested && $this->stopDeadline !== null && microtime(true) >= $this->stopDeadline && !$this->sigtermSent) {
            $proc->signal(\SIGTERM);
            $this->sigtermSent = true;
        }

        return null;
    }

    private function resetStopState(): void
    {
        $this->stopRequested = false;
        $this->stopDeadline = null;
        $this->sigtermSent = false;
    }

    /**
     * Stop recording gracefully (blocking). Sends SIGINT then polls until process has exited.
     *
     * @return string|null The path to the recorded WAV file, or null if not recording
     * @throws RuntimeException if the process had already exited with an error (e.g. crash)
     */
    public function stop(): ?string
    {
        if (!$this->recording) {
            return $this->currentFilePath;
        }

        $this->requestStop();
        while (($path = $this->pollStop()) === null) {
            usleep(50_000);
        }

        return $path;
    }

    public function isRecording(): bool
    {
        return $this->recording;
    }

    public function getCurrentFilePath(): ?string
    {
        return $this->currentFilePath;
    }

    /**
     * Check if ffmpeg is available (for pre-flight checks or tests).
     */
    public static function isFfmpegAvailable(?string $ffmpegPath = null): bool
    {
        $binary = $ffmpegPath ?? 'ffmpeg';
        $process = Process::fromShellCommandline(
            sprintf('command -v %s 2>/dev/null', escapeshellarg($binary))
        );
        $process->run();

        if ($process->isSuccessful() && trim($process->getOutput()) !== '') {
            return true;
        }

        if ($ffmpegPath !== null) {
            return is_executable($ffmpegPath);
        }

        return false;
    }

    private function createDefaultOutputPath(): string
    {
        $timestamp = (new \DateTimeImmutable())->format('Ymd-His');
        $random = bin2hex(random_bytes(4));
        $dir = $this->projectDir . '/var/voice';

        return $dir . '/' . $timestamp . '-' . $random . '.wav';
    }

    /**
     * @return list<string>
     */
    private function buildFfmpegCommand(string $outputPath): array
    {
        $ffmpeg = $this->ffmpegPath ?? 'ffmpeg';

        return [
            $ffmpeg,
            '-hide_banner',
            '-loglevel', 'error',
            '-y',
            '-f', 'avfoundation',
            '-i', $this->buildAvfoundationInput(),
            '-ac', (string) self::CHANNELS,
            '-ar', (string) self::SAMPLE_RATE,
            '-c:a', 'pcm_s16le',
            $outputPath,
        ];
    }

    private function buildAvfoundationInput(): string
    {
        return ':' . $this->avfoundationAudioDevice;
    }
}
