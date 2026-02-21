<?php

declare(strict_types=1);

namespace App\Command;

use App\Flow\InputProviderFlow;
use App\Message\VoiceControlMessage;
use App\Model\VoiceControlEvent;
use App\Service\VoiceTransportProvider;
use App\Service\VoiceWorkerRegistry;
use Flow\Driver\AmpDriver;
use Flow\DriverInterface;
use Flow\Driver\FiberDriver;
use Flow\ExceptionInterface;
use Flow\Flow\Flow;
use Flow\Flow\TransportFlow;
use Flow\FlowFactory;
use Flow\Ip;
use Flow\IpStrategy\LinearIpStrategy;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;

#[AsCommand(
    name: 'voice:worker',
    description: 'Run a voice worker that consumes control messages for a session',
)]
final class VoiceWorkerCommand extends Command
{
    private const HEARTBEAT_INTERVAL_SECONDS = 1000000; // 1 second
    private const INPUT_PROVIDER_INTERVALE_SECONDS = 1; // 1 second

    public function __construct(
        private readonly VoiceTransportProvider $transportProvider,
        private readonly VoiceWorkerRegistry $registry,
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

        $driver = new FiberDriver();

        $inputProviderFlow = new InputProviderFlow($driver, $receiver);
        $displayFlow = static function (VoiceControlEvent $event) use ($sessionId, $io): VoiceControlEvent {
            $io->writeln(sprintf(
                '[%s] session=%s received type=%s at=%s -> InputProviderFlow produced type=%s at=%s',
                date('Y-m-d H:i:s'),
                $sessionId,
                $event->type->value,
                $event->at->format(\DateTimeInterface::ATOM),
                $event->type->value,
                $event->at->format(\DateTimeInterface::ATOM),
            ));

            return $event;
        };

        $flow = (new FlowFactory())
            ->create(static function () use ($inputProviderFlow, $displayFlow): \Generator {
                yield $inputProviderFlow;
                yield $displayFlow;
            }, [
                'driver' => $driver,
            ]);

        $cleanupInputProvider = $inputProviderFlow->tick(self::INPUT_PROVIDER_INTERVALE_SECONDS);
        $cleanupHeartbeat = $driver->tick(self::HEARTBEAT_INTERVAL_SECONDS, function () use ($sessionId): void {
            $this->registry->heartbeat($sessionId);
        });

        $onStop = function () use ($cleanupInputProvider, $cleanupHeartbeat, $sessionId, $io): void {
            $cleanupHeartbeat();
            $cleanupInputProvider();

            $this->registry->unregister($sessionId);
            $io->info(sprintf('Unregistered session "%s".', $sessionId));
            exit(0);
        };
        pcntl_async_signals(true);
        pcntl_signal(SIGINT, $onStop);
        pcntl_signal(SIGTERM, $onStop);

        try {
            $flow->await();
        } finally {
            $this->registry->unregister($sessionId);
            $io->info(sprintf('Unregistered session "%s".', $sessionId));
        }

        return Command::SUCCESS;
    }

    private function generateSessionId(): string
    {
        return bin2hex(random_bytes(8));
    }
}
