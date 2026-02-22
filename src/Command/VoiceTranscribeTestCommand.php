<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\WhisperCpp;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'voice:transcribe-test',
    description: 'Test WhisperCpp service: transcribe a WAV file and print the result',
)]
final class VoiceTranscribeTestCommand extends Command
{
    public function __construct(
        private readonly WhisperCpp $whisperCpp,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument(
            'wav',
            InputArgument::REQUIRED,
            'Path to a 16-bit PCM WAV file (16 kHz mono, e.g. from voice:record-test)',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $wavPath = $input->getArgument('wav');

        $io->info(sprintf('Transcribing: %s', $wavPath));

        try {
            $text = $this->whisperCpp->transcribe($wavPath);
        } catch (\Throwable $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }

        $io->success('Transcription:');
        $io->writeln($text !== '' ? $text : '(empty)');

        return Command::SUCCESS;
    }
}
