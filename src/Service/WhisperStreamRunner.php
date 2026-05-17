<?php

declare(strict_types=1);

namespace App\Service;

use App\Service\WhisperStream\WhisperStreamOutputParser;
use App\Service\WhisperStream\WhisperStreamPollResult;
use App\Service\WhisperStream\WhisperStreamStopResult;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Manages a whisper-stream subprocess (SDL2 mic capture + VAD transcription).
 */
final class WhisperStreamRunner
{
    private ?Process $process = null;

    private string $sessionId = '';

    private WhisperStreamOutputParser $parser;

    private string $transcriptPath = '';

    private string $workDir = '';

    private int $transcriptFileOffset = 0;

    private ?\DateTimeImmutable $startedAt = null;

    public function __construct(
        private readonly string $projectDir,
        private readonly string $streamBinaryPath,
        private readonly string $modelPath,
        private readonly string $language,
        private readonly int $lengthMs,
        private readonly int $stepMs,
        private readonly float $vadThreshold,
        private readonly int $threads,
        private readonly int $captureId = -1,
    ) {
        $this->parser = new WhisperStreamOutputParser();
    }

    public function isRunning(): bool
    {
        return $this->process !== null && $this->process->isRunning();
    }

    public function start(string $sessionId): void
    {
        if ($this->isRunning()) {
            throw new RuntimeException('WhisperStreamRunner is already running.');
        }

        if ($this->streamBinaryPath === '' || !is_executable($this->streamBinaryPath)) {
            throw new RuntimeException(sprintf(
                'whisper-stream binary not found or not executable: %s (set WHISPER_STREAM_PATH and build with WHISPER_SDL2=ON)',
                $this->streamBinaryPath,
            ));
        }

        if ($this->modelPath === '' || !is_file($this->modelPath)) {
            throw new RuntimeException(sprintf('Whisper model not found: %s', $this->modelPath));
        }

        $this->sessionId = $sessionId;
        $this->startedAt = new \DateTimeImmutable();
        $this->transcriptFileOffset = 0;
        $this->parser->reset();
        $this->workDir = $this->projectDir . '/var/voice';
        if (!is_dir($this->workDir) && !@mkdir($this->workDir, 0755, true)) {
            throw new RuntimeException(sprintf('Cannot create directory: %s', $this->workDir));
        }

        $this->transcriptPath = sprintf('%s/stream-%s.txt', $this->workDir, $sessionId);
        if (is_file($this->transcriptPath)) {
            @unlink($this->transcriptPath);
        }

        $command = $this->buildCommand();

        $this->process = new Process($command, $this->workDir, null, null, null);
        $this->process->setTimeout(null);
        $this->process->setPty(true);
        $this->process->start();

        if (!$this->process->isRunning()) {
            $err = trim($this->process->getErrorOutput()) ?: 'process exited immediately';
            $this->process = null;
            throw new RuntimeException(sprintf('Failed to start whisper-stream: %s', $err));
        }
    }

    public function poll(): WhisperStreamPollResult
    {
        if (!$this->isRunning()) {
            return new WhisperStreamPollResult();
        }

        $chunk = $this->process->getIncrementalOutput() . $this->process->getIncrementalErrorOutput();
        $chunk .= $this->readTranscriptFileDelta();

        $newPartials = $this->parser->feed($chunk);

        return new WhisperStreamPollResult(
            $newPartials,
            $this->parser->getAccumulatedText(),
        );
    }

    public function stop(): WhisperStreamStopResult
    {
        if (!$this->isRunning()) {
            return new WhisperStreamStopResult(
                $this->parser->getAccumulatedText(),
                $this->findLatestWavPath(),
                $this->transcriptPath,
            );
        }

        $this->process->interrupt();
        try {
            $this->process->wait();
        } catch (\Throwable) {
            $this->process->stop(0, SIGKILL);
            $this->process->wait();
        }

        $remaining = $this->process->getOutput() . $this->process->getErrorOutput();
        $finalPartials = $this->parser->feed($remaining);

        if (is_file($this->transcriptPath)) {
            $finalPartials = array_merge($finalPartials, $this->parser->feed((string) file_get_contents($this->transcriptPath)));
        }

        $this->process = null;
        $fullText = $this->parser->getAccumulatedText();
        $wavPath = $this->findLatestWavPath();

        return new WhisperStreamStopResult($fullText, $wavPath, $this->transcriptPath, $finalPartials);
    }

    /**
     * @return list<string>
     */
    private function buildCommand(): array
    {
        $args = [
            $this->streamBinaryPath,
            '-m', $this->modelPath,
            '-l', $this->language,
            '-t', (string) $this->threads,
            // step > 0 = sliding window (live updates); step 0 = VAD (text after silence only)
            '--step', (string) $this->stepMs,
            '--length', (string) $this->lengthMs,
            '-vth', (string) $this->vadThreshold,
            '-f', $this->transcriptPath,
            '-sa',
        ];

        if ($this->captureId >= 0) {
            $args[] = '-c';
            $args[] = (string) $this->captureId;
        }

        $stdbuf = $this->resolveStdbufPath();
        if ($stdbuf !== null) {
            return array_merge([$stdbuf, '-oL', '-eL'], $args);
        }

        return $args;
    }

    private function resolveStdbufPath(): ?string
    {
        foreach (['/usr/bin/stdbuf', '/opt/homebrew/opt/coreutils/libexec/gnubin/stdbuf'] as $path) {
            if (is_executable($path)) {
                return $path;
            }
        }

        $process = new Process(['command', '-v', 'stdbuf']);
        $process->run();

        if ($process->isSuccessful()) {
            $path = trim($process->getOutput());
            if ($path !== '' && is_executable($path)) {
                return $path;
            }
        }

        return null;
    }

    private function findLatestWavPath(): string
    {
        $files = glob($this->workDir . '/*.wav') ?: [];
        if ($files === []) {
            return '';
        }

        $cutoff = ($this->startedAt ?? new \DateTimeImmutable())->getTimestamp() - 5;
        $files = array_values(array_filter(
            $files,
            static fn (string $path): bool => is_file($path) && filemtime($path) >= $cutoff,
        ));
        if ($files === []) {
            return '';
        }

        usort($files, static fn (string $a, string $b): int => filemtime($b) <=> filemtime($a));

        return $files[0];
    }

    private function readTranscriptFileDelta(): string
    {
        if (!is_file($this->transcriptPath)) {
            return '';
        }

        $size = filesize($this->transcriptPath);
        if ($size === false || $size <= $this->transcriptFileOffset) {
            return '';
        }

        $handle = fopen($this->transcriptPath, 'rb');
        if ($handle === false) {
            return '';
        }

        fseek($handle, $this->transcriptFileOffset);
        $delta = fread($handle, $size - $this->transcriptFileOffset);
        fclose($handle);

        if (!\is_string($delta)) {
            return '';
        }

        $this->transcriptFileOffset = $size;

        return $delta;
    }
}
