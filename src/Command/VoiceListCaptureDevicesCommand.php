<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\WhisperCaptureDeviceLister;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'voice:list-capture-devices',
    description: 'List microphone devices for batch (ffmpeg) and stream (whisper-stream / SDL2)',
)]
final class VoiceListCaptureDevicesCommand extends Command
{
    public function __construct(
        private readonly WhisperCaptureDeviceLister $lister,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Capture devices');
        $io->note('whisper-cli only transcribes WAV files — it does not capture a microphone.');

        $ffmpegDevices = $this->lister->listFfmpegAvfoundationAudioDevices();
        $io->section('Batch mode (ffmpeg / FLOWVOX_WHISPER_MODE=batch)');
        $io->text('Set <info>WHISPER_FFMPEG_CAPTURE_DEVICE=N</info> — avfoundation audio index (input <info>:N</info>).');
        if ($ffmpegDevices === []) {
            $io->warning('No ffmpeg devices listed (install ffmpeg, or run on macOS with avfoundation).');
        } else {
            $rows = array_map(static fn (array $d): array => [(string) $d['id'], $d['name']], $ffmpegDevices);
            $io->table(['ID', 'Name'], $rows);
        }

        $streamDevices = $this->lister->listWhisperStreamCaptureDevices();
        $io->section('Stream mode (whisper-stream / SDL2)');
        $io->text('Set <info>WHISPER_STREAM_CAPTURE_ID=N</info> — passed as <info>-c N</info> to whisper-stream.');
        if ($streamDevices === []) {
            $io->warning('Could not list SDL devices (check WHISPER_STREAM_PATH and WHISPER_MODEL_PATH).');
        } else {
            $rows = array_map(static fn (array $d): array => [(string) $d['id'], $d['name']], $streamDevices);
            $io->table(['ID', 'Name'], $rows);
        }

        return Command::SUCCESS;
    }
}
