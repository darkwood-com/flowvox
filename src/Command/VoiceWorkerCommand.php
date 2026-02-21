<?php

declare(strict_types=1);

namespace App\Command;

use App\Flow\FlowRuntime;
use App\Message\VoiceControlMessage;
use App\Model\VoiceControlEvent;
use App\Service\VoiceTransportProvider;
use App\Service\VoiceWorkerRegistry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;

#[AsCommand(
    name: 'voice:worker',
    description: 'Run a voice worker that consumes control messages for a session',
)]
final class VoiceWorkerCommand extends Command
{
    private const HEARTBEAT_INTERVAL_SECONDS = 5;

    public function __construct(
        private readonly VoiceTransportProvider $transportProvider,
        private readonly VoiceWorkerRegistry $registry,
        private readonly FlowRuntime $flowRuntime,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('session', 's', InputOption::VALUE_REQUIRED, 'Session ID (default: generated UUID)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $sessionId = $input->getOption('session') ?? $this->generateSessionId();

        $this->registry->register($sessionId, getmypid() ?: 0);
        $io->info(sprintf('Registered session "%s" (PID %d). Consuming...', $sessionId, getmypid()));

        $receiver = $this->transportProvider->getTransportForSession($sessionId);
        if (!$receiver instanceof ReceiverInterface) {
            $io->error('Transport does not support receiving.');
            return Command::FAILURE;
        }

        $running = true;
        $lastHeartbeat = time();
        $signalHandler = function () use (&$running): void {
            $running = false;
        };
        pcntl_async_signals(true);
        pcntl_signal(SIGINT, $signalHandler);
        pcntl_signal(SIGTERM, $signalHandler);

        try {
            while ($running) {
                $now = time();
                if ($now - $lastHeartbeat >= self::HEARTBEAT_INTERVAL_SECONDS) {
                    $this->registry->heartbeat($sessionId);
                    $lastHeartbeat = $now;
                }

                $envelopes = $receiver->get();
                foreach ($envelopes as $envelope) {
                    if (!$running) {
                        break;
                    }
                    $this->handleEnvelope($envelope, $sessionId, $io, $receiver);
                }

                if (!$running) {
                    break;
                }
                usleep(100_000); // 100ms poll when idle
            }
        } finally {
            $this->registry->unregister($sessionId);
            $io->info(sprintf('Unregistered session "%s".', $sessionId));
        }

        return Command::SUCCESS;
    }

    private function handleEnvelope(
        Envelope $envelope,
        string $sessionId,
        SymfonyStyle $io,
        ReceiverInterface $receiver,
    ): void {
        $message = $envelope->getMessage();
        if (!$message instanceof VoiceControlMessage) {
            $receiver->reject($envelope);
            return;
        }

        try {
            $event = new VoiceControlEvent($message->type, $message->at);
            $outputEvent = $this->flowRuntime->runInputProvider($event);

            $io->writeln(sprintf(
                '[%s] session=%s received type=%s at=%s -> InputProviderFlow produced type=%s at=%s',
                date('Y-m-d H:i:s'),
                $sessionId,
                $message->type->value,
                $message->at->format(\DateTimeInterface::ATOM),
                $outputEvent->type->value,
                $outputEvent->at->format(\DateTimeInterface::ATOM),
            ));
            $receiver->ack($envelope);
        } catch (\Throwable $e) {
            $io->error(sprintf('Handler failed: %s', $e->getMessage()));
            $receiver->reject($envelope);
        }
    }

    private function generateSessionId(): string
    {
        return bin2hex(random_bytes(8));
    }
}
