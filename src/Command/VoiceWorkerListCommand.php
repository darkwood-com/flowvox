<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\VoiceWorkerRegistry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'voice:worker-list',
    description: 'List active voice worker sessions',
)]
final class VoiceWorkerListCommand extends Command
{
    public function __construct(
        private readonly VoiceWorkerRegistry $registry,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'clean-stale',
            null,
            InputOption::VALUE_NONE,
            'Remove sessions with stale heartbeat (TTL 30s) and print removed count',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ($input->getOption('clean-stale')) {
            $removed = $this->registry->cleanStale(30);
            $io->writeln(sprintf('Removed %d stale session(s).', $removed));
        }

        $sessions = $this->registry->listActiveSessions(30);
        if ($sessions === []) {
            $io->writeln('No active sessions.');
            return Command::SUCCESS;
        }

        $rows = array_map(
            static fn (array $s): array => [
                $s['id'],
                $s['pid'],
                $s['startedAt']->format('Y-m-d H:i:s'),
                $s['lastHeartbeatAt']->format('Y-m-d H:i:s'),
            ],
            $sessions,
        );
        $io->table(['Session ID', 'PID', 'Started at', 'Last heartbeat'], $rows);

        return Command::SUCCESS;
    }
}
