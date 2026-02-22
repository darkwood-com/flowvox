<?php

declare(strict_types=1);

namespace App\Service;

use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Transcribes WAV files using the whisper.cpp CLI (whisper-cli).
 *
 * Runs the binary via Symfony Process and returns the transcription from stdout.
 * Expects 16-bit PCM WAV, 16 kHz, mono (e.g. output from VoiceRecorder).
 */
final class WhisperCpp
{
    /** CLI flag: model path (-m) */
    public const CMD_MODEL = '-m';
    /** CLI flag: input file (-f) */
    public const CMD_FILE = '-f';
    /** CLI flag: no timestamps on stdout (-nt) */
    public const CMD_NO_TIMESTAMPS = '-nt';
    /** CLI flag: no extra prints / quiet stderr (-np) */
    public const CMD_NO_PRINTS = '-np';
    /** CLI flag: language, e.g. "en" or "auto" (-l) */
    public const CMD_LANGUAGE = '-l';
    /** CLI flag: threads (-t) */
    public const CMD_THREADS = '-t';

    public function __construct(
        private readonly string $whisperCliPath,
        private readonly string $modelPath,
    ) {
    }

    /**
     * Transcribe a WAV file. Returns the transcribed text (stdout of whisper-cli).
     *
     * @param string $wavFilePath Absolute or relative path to a 16-bit PCM WAV (16 kHz mono)
     * @return string Trimmed transcription
     * @throws RuntimeException if file is missing, process fails, or binary is not runnable
     */
    public function transcribe(string $wavFilePath): string
    {
        if (!is_file($wavFilePath) || !is_readable($wavFilePath)) {
            throw new RuntimeException(sprintf('WAV file is not readable: %s', $wavFilePath));
        }

        $command = $this->buildCommand($wavFilePath);
        $process = new Process($command, null, null, null, null);
        $process->run();

        if (!$process->isSuccessful()) {
            $stderr = $process->getErrorOutput();
            throw new RuntimeException(sprintf(
                'whisper-cli failed (exit %d): %s',
                $process->getExitCode(),
                $stderr !== '' ? trim($stderr) : 'no stderr'
            ));
        }

        return trim($process->getOutput());
    }

    /**
     * Build the whisper-cli command array. Override or extend in tests.
     *
     * @return list<string>
     */
    protected function buildCommand(string $wavFilePath): array
    {
        return [
            $this->whisperCliPath,
            self::CMD_MODEL,
            $this->modelPath,
            self::CMD_FILE,
            $wavFilePath,
            self::CMD_NO_TIMESTAMPS,
            self::CMD_NO_PRINTS,
            self::CMD_LANGUAGE,
            'fr'
        ];
    }
}
