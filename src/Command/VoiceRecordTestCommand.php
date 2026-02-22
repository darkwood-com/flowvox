<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\VoiceRecorder;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;

#[AsCommand(
    name: 'voice:record-test',
    description: 'Test voice recording: record for N seconds then print WAV path',
)]
final class VoiceRecordTestCommand extends Command
{
    public function __construct(
        private readonly VoiceRecorder $voiceRecorder,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('seconds', null, InputOption::VALUE_REQUIRED, 'Seconds to record', '5');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $seconds = (int) $input->getOption('seconds');

        if ($seconds <= 0) {
            $io->error('Option --seconds must be a positive integer.');
            return Command::FAILURE;
        }

        if (!VoiceRecorder::isFfmpegAvailable()) {
            $io->error('ffmpeg is not available. Install it (e.g. brew install ffmpeg).');
            return Command::FAILURE;
        }

        $io->info(sprintf('Starting recording for %d second(s)...', $seconds));

        try {
            $path = $this->voiceRecorder->start();
            $io->writeln(sprintf('Recording to: %s', $path));
        } catch (\Throwable $e) {
            $io->error('Failed to start recorder: ' . $e->getMessage());
            return Command::FAILURE;
        }

        sleep($seconds);

        try {
            $stoppedPath = $this->voiceRecorder->stop();
            $io->success(sprintf('Stopped. WAV file: %s', $stoppedPath ?? $path));
        } catch (\Throwable $e) {
            $io->error('Failed to stop recorder: ' . $e->getMessage());
            return Command::FAILURE;
        }

        $wavPath = $stoppedPath ?? $path;
        if ($wavPath !== null && is_file($wavPath)) {
            $this->printStreamInfo($io, $wavPath);
        }

        return Command::SUCCESS;
    }

    private function printStreamInfo(SymfonyStyle $io, string $wavPath): void
    {
        $process = new Process(['ffprobe', '-v', 'error', '-show_entries', 'stream=codec_name,sample_rate,channels', '-of', 'default=noprint_wrappers=1', $wavPath]);
        $process->run();
        if ($process->isSuccessful() && $process->getOutput() !== '') {
            $io->section('Stream info (ffprobe)');
            $io->writeln(trim($process->getOutput()));
        }
    }
}
