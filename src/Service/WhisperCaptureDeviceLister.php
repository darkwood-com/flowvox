<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\Process\Process;

/**
 * Lists microphone capture devices for batch (ffmpeg avfoundation) and stream (whisper-stream / SDL2).
 */
final class WhisperCaptureDeviceLister
{
    public function __construct(
        private readonly ?string $ffmpegPath = null,
        private readonly string $streamBinaryPath = '',
        private readonly string $modelPath = '',
    ) {
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    public function listFfmpegAvfoundationAudioDevices(): array
    {
        if (!VoiceRecorder::isFfmpegAvailable($this->ffmpegPath)) {
            return [];
        }

        $ffmpeg = $this->ffmpegPath ?? 'ffmpeg';
        $process = new Process([
            $ffmpeg,
            '-hide_banner',
            '-f', 'avfoundation',
            '-list_devices', 'true',
            '-i', '',
        ]);
        $process->run();

        return $this->parseFfmpegAvfoundationList($process->getErrorOutput());
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    public function listWhisperStreamCaptureDevices(): array
    {
        if ($this->streamBinaryPath === '' || !is_executable($this->streamBinaryPath)) {
            return [];
        }

        if ($this->modelPath === '' || !is_file($this->modelPath)) {
            return [];
        }

        $process = new Process([
            $this->streamBinaryPath,
            '-m', $this->modelPath,
            '-l', 'en',
            '--step', '0',
            '--length', '1000',
        ]);
        $process->setTimeout(3);
        $process->start();

        $stderr = '';
        $deadline = microtime(true) + 2.5;
        while ($process->isRunning() && microtime(true) < $deadline) {
            $stderr .= $process->getIncrementalErrorOutput();
            if (str_contains($stderr, 'obtained spec for input device')) {
                break;
            }
            usleep(50_000);
        }

        if ($process->isRunning()) {
            $process->stop(1, \SIGINT);
        }

        $stderr .= $process->getErrorOutput();

        return $this->parseSdlCaptureList($stderr);
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    private function parseFfmpegAvfoundationList(string $output): array
    {
        $devices = [];
        $seenIds = [];
        $inAudio = false;

        foreach (preg_split('/\r\n|\r|\n/', $output) ?: [] as $line) {
            if (str_contains($line, 'AVFoundation audio devices:')) {
                $inAudio = true;
                continue;
            }
            if ($inAudio && str_contains($line, 'AVFoundation video devices:')) {
                break;
            }
            if ($inAudio && preg_match('/\[(\d+)\]\s+(.+)$/', $line, $m)) {
                $id = (int) $m[1];
                if (isset($seenIds[$id])) {
                    continue;
                }
                $seenIds[$id] = true;
                $devices[] = ['id' => $id, 'name' => trim($m[2])];
            }
        }

        return $devices;
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    private function parseSdlCaptureList(string $stderr): array
    {
        $devices = [];
        $seenIds = [];
        foreach (preg_split('/\r\n|\r|\n/', $stderr) ?: [] as $line) {
            if (preg_match("/Capture device #(\d+):\s+'([^']+)'/", $line, $m)) {
                $id = (int) $m[1];
                if (isset($seenIds[$id])) {
                    continue;
                }
                $seenIds[$id] = true;
                $devices[] = ['id' => $id, 'name' => $m[2]];
            }
        }

        return $devices;
    }
}
