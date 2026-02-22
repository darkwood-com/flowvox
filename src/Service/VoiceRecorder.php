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
    private const AVFOUNDATION_INPUT = ':0';
    private const SAMPLE_RATE = 16000;
    private const CHANNELS = 1;
    private const GRACEFUL_WAIT_SECONDS = 3;

    private ?Process $process = null;
    private bool $recording = false;
    private ?string $currentFilePath = null;
    private ?\DateTimeImmutable $startedAt = null;

    public function __construct(
        private readonly string $projectDir,
        private readonly ?string $ffmpegPath = null,
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
     * Stop recording gracefully. Sends SIGINT first (so ffmpeg finalizes WAV header), then SIGTERM if needed.
     *
     * @return string|null The path to the recorded WAV file, or null if not recording
     * @throws RuntimeException if the process had already exited with an error (e.g. crash)
     */
    public function stop(): ?string
    {
        if (!$this->recording) {
            return $this->currentFilePath;
        }

        $path = $this->currentFilePath;
        $proc = $this->process;

        if ($proc === null) {
            $this->recording = false;
            return $path;
        }

        if (!$proc->isRunning()) {
            $this->process = null;
            $this->recording = false;
            $exitCode = $proc->getExitCode();
            if ($exitCode !== null && $exitCode !== 0) {
                throw new RuntimeException(sprintf(
                    'ffmpeg exited unexpectedly with code %d: %s',
                    $exitCode,
                    $proc->getErrorOutput() ?: 'no stderr'
                ));
            }
            return $path;
        }

        $proc->signal(\SIGINT);
        $deadline = microtime(true) + self::GRACEFUL_WAIT_SECONDS;
        while ($proc->isRunning() && microtime(true) < $deadline) {
            usleep(50_000);
        }
        if ($proc->isRunning()) {
            $proc->stop(2, \SIGTERM);
        }

        $this->process = null;
        $this->recording = false;

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
            '-i', self::AVFOUNDATION_INPUT,
            '-ac', (string) self::CHANNELS,
            '-ar', (string) self::SAMPLE_RATE,
            '-c:a', 'pcm_s16le',
            $outputPath,
        ];
    }
}
