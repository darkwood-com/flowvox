<?php

declare(strict_types=1);

namespace App\Command;

use App\Application\Transcription\TranscriptionProviderRegistry;
use App\Domain\Enum\VoiceDomainEventType;
use App\Flow\InputProviderFlow;
use App\Flow\RecorderFlow;
use App\Flow\TranscribeFlow;
use App\Model\RecordingFinished;
use App\Model\TranscriptionChunk;
use App\Model\VoiceControlEvent;
use App\Service\VoiceRecorder;
use App\Service\VoiceTransportProvider;
use App\Service\WhisperMode;
use App\Service\WhisperStreamRunner;
use App\Service\VoiceWorkerRegistry;
use App\Service\WorkerEventEmitter;
use Flow\Driver\FiberDriver;
use Flow\FlowFactory;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
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
        private readonly VoiceRecorder $voiceRecorder,
        private readonly WhisperStreamRunner $whisperStreamRunner,
        private readonly TranscriptionProviderRegistry $providerRegistry,
        private readonly WorkerEventEmitter $eventEmitter,
        private readonly LoggerInterface $logger,
        private readonly string $whisperMode,
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

        $this->eventEmitter->bindSession($sessionId);
        $this->registry->register($sessionId, getmypid() ?: 0);
        $io->info(sprintf('Registered session "%s" (PID %d). Consuming...', $sessionId, getmypid()));

        $receiver = $this->transportProvider->getTransportForSession($sessionId);
        if (!$receiver instanceof ReceiverInterface) {
            $io->error('Transport does not support receiving.');
            return Command::FAILURE;
        }

        $driver = new FiberDriver();

        $inputProviderFlow = new InputProviderFlow($driver, $receiver);
        $displayFlow = function (VoiceControlEvent|RecordingFinished|TranscriptionChunk $data) use ($sessionId, $io): VoiceControlEvent|RecordingFinished|TranscriptionChunk {
            if ($data instanceof VoiceControlEvent) {
                $io->writeln(sprintf(
                    '[%s] session=%s received type=%s at=%s',
                    date('Y-m-d H:i:s'),
                    $sessionId,
                    $data->type->value,
                    $data->at->format(\DateTimeInterface::ATOM),
                ));
            } elseif ($data instanceof RecordingFinished) {
                $io->writeln(sprintf('[%s] session=%s RecorderFlow emitted RecordingFinished wav=%s', date('Y-m-d H:i:s'), $sessionId, $data->wavPath));
            } else {
                $preview = mb_strlen($data->text) > 120 ? mb_substr($data->text, 0, 120) . '…' : $data->text;
                $io->writeln(sprintf('[%s] session=%s TranscribeFlow emitted TranscriptionChunk: %s', date('Y-m-d H:i:s'), $sessionId, $preview));
            }

            return $data;
        };
        $useWhisperStream = WhisperMode::fromEnv($this->whisperMode) === WhisperMode::Stream;
        if ($useWhisperStream) {
            $io->note('Whisper stream mode: microphone captured by whisper-stream (SDL2).');
        }
        $recorderFlow = new RecorderFlow(
            $driver,
            $this->voiceRecorder,
            $this->logger,
            $useWhisperStream,
            $this->whisperStreamRunner,
            $sessionId,
            $this->eventEmitter,
        );
        $transcribeFlow = new TranscribeFlow($driver, $this->providerRegistry, $sessionId, $this->logger, $this->eventEmitter);

        $flow = (new FlowFactory())
            ->create(static function () use (
                $inputProviderFlow,
                $recorderFlow,
                $transcribeFlow,
                $displayFlow
            ): \Generator {
                yield $inputProviderFlow;
                yield $displayFlow;
                yield $recorderFlow;
                yield $displayFlow;
                yield $transcribeFlow;
                yield $displayFlow;
            }, [
                'driver' => $driver,
            ]);

        $cleanupInputProvider = $inputProviderFlow->tick(self::INPUT_PROVIDER_INTERVALE_SECONDS);
        $cleanupHeartbeat = $driver->tick(self::HEARTBEAT_INTERVAL_SECONDS, function () use ($sessionId): void {
            $this->registry->heartbeat($sessionId);
            $this->eventEmitter->emit(VoiceDomainEventType::Heartbeat);
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
