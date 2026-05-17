<?php

declare(strict_types=1);

namespace App\Application\UseCase;

use App\Application\Port\VoiceEventPublisherPort;
use App\Domain\Event\VoiceDomainEvent;
use App\Infrastructure\Persistence\DoctrineVoiceEventStore;
use Psr\Log\LoggerInterface;

final readonly class RecordWorkerEvent
{
    public function __construct(
        private DoctrineVoiceEventStore $eventStore,
        private VoiceEventPublisherPort $publisher,
        private LoggerInterface $logger,
    ) {
    }

    public function execute(VoiceDomainEvent $event): void
    {
        // Mercure first so the UI gets live updates even if persistence fails.
        $this->publisher->publish($event);

        try {
            $this->eventStore->record($event);
        } catch (\Throwable $e) {
            $this->logger->warning('Failed to persist worker event (Mercure was published): {message}', [
                'message' => $e->getMessage(),
                'type' => $event->type->value,
                'sessionId' => $event->sessionId,
            ]);
        }
    }
}
