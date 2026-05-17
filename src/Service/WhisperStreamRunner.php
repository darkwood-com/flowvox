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

    public function __construct(
        private readonly string $projectDir,
        private readonly string $streamBinaryPath,
        private readonly string $modelPath,
        private readonly string $language,
        private readonly int $lengthMs,
        private readonly float $vadThreshold,
        private readonly int $threads,
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

        $command = [
            $this->streamBinaryPath,
            '-m', $this->modelPath,
            '-l', $this->language,
            '-t', (string) $this->threads,
            '--step', '0',
            '--length', (string) $this->lengthMs,
            '-vth', (string) $this->vadThreshold,
            '-f', $this->transcriptPath,
            '-sa',
        ];

        $this->process = new Process($command, $this->workDir, null, null, null);
        $this->process->setTimeout(null);
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
        $this->parser->feed($remaining);

        if (is_file($this->transcriptPath)) {
            $this->parser->feed((string) file_get_contents($this->transcriptPath));
        }

        $this->process = null;
        $fullText = $this->parser->getAccumulatedText();
        $wavPath = $this->findLatestWavPath();

        return new WhisperStreamStopResult($fullText, $wavPath, $this->transcriptPath);
    }

    private function findLatestWavPath(): string
    {
        $files = glob($this->workDir . '/*.wav') ?: [];
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
