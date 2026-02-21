<?php

declare(strict_types=1);

namespace App\Command;

use App\Enum\VoiceControlType;
use App\Message\VoiceControlMessage;
use App\Service\VoiceTransportProvider;
use App\Service\VoiceWorkerRegistry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\Envelope;

#[AsCommand(
    name: 'voice:start',
    description: 'Send START to a session or broadcast to all active sessions',
)]
final class VoiceStartCommand extends Command
{
    public function __construct(
        private readonly VoiceTransportProvider $transportProvider,
        private readonly VoiceWorkerRegistry $registry,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('session', 's', InputOption::VALUE_REQUIRED, 'Target session ID (omit to broadcast to all)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $sessionOption = $input->getOption('session');

        $sessions = $sessionOption !== null
            ? [['id' => $sessionOption]]
            : $this->registry->listActiveSessions(30);

        if ($sessions === []) {
            $io->warning($sessionOption !== null
                ? sprintf('No active session with id "%s".', $sessionOption)
                : 'No active sessions to broadcast to.');
            return Command::SUCCESS;
        }

        $message = new VoiceControlMessage(VoiceControlType::START, new \DateTimeImmutable());
        $envelope = Envelope::wrap($message);
        $sent = 0;

        foreach ($sessions as $session) {
            $sessionId = $session['id'];
            try {
                $transport = $this->transportProvider->getTransportForSession($sessionId);
                $transport->send($envelope);
                $io->writeln(sprintf('  Sent START -> %s', $sessionId));
                $sent++;
            } catch (\Throwable $e) {
                $io->error(sprintf('  Failed to send to %s: %s', $sessionId, $e->getMessage()));
            }
        }

        $io->success(sprintf('Targeted %d session(s), sent %d.', count($sessions), $sent));
        return Command::SUCCESS;
    }
}
