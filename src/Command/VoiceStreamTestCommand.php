<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\WhisperStreamRunner;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'voice:stream-test',
    description: 'Test whisper-stream: record from microphone for N seconds and print parsed output',
)]
final class VoiceStreamTestCommand extends Command
{
    public function __construct(
        private readonly WhisperStreamRunner $streamRunner,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('seconds', null, InputOption::VALUE_REQUIRED, 'Seconds to run', '15');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $seconds = max(1, (int) $input->getOption('seconds'));

        $io->title('whisper-stream test');
        $io->note(sprintf('Recording for %d seconds — speak into the microphone.', $seconds));

        try {
            $this->streamRunner->start('stream-test');
        } catch (\Throwable $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $end = time() + $seconds;
        while (time() < $end && $this->streamRunner->isRunning()) {
            $poll = $this->streamRunner->poll();
            foreach ($poll->newPartials as $partial) {
                $io->writeln('<info>[partial]</info> ' . $partial);
            }
            usleep(300_000);
        }

        $result = $this->streamRunner->stop();

        $io->success('Stopped.');
        $io->section('Full text');
        $io->writeln($result->fullText !== '' ? $result->fullText : '(empty)');
        if ($result->wavPath !== '') {
            $io->writeln(sprintf('WAV: %s', $result->wavPath));
        }
        if ($result->transcriptPath !== '') {
            $io->writeln(sprintf('Transcript file: %s', $result->transcriptPath));
        }

        return Command::SUCCESS;
    }
}
