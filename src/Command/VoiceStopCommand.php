<?php

declare(strict_types=1);

namespace App\Command;

use App\Application\UseCase\SendVoiceControl;
use App\Enum\VoiceControlType;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'voice:stop',
    description: 'Send STOP to a session or broadcast to all active sessions',
)]
final class VoiceStopCommand extends Command
{
    public function __construct(
        private readonly SendVoiceControl $sendVoiceControl,
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

        $sent = $this->sendVoiceControl->execute(VoiceControlType::STOP, $sessionOption);

        if ($sent === []) {
            $io->warning($sessionOption !== null
                ? sprintf('No active session with id "%s".', $sessionOption)
                : 'No active sessions to broadcast to.');
            return Command::SUCCESS;
        }

        foreach ($sent as $sessionId) {
            $io->writeln(sprintf('  Sent STOP -> %s', $sessionId));
        }

        $io->success(sprintf('Sent STOP to %d session(s).', \count($sent)));

        return Command::SUCCESS;
    }
}
