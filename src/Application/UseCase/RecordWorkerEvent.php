<?php

declare(strict_types=1);

namespace App\Application\UseCase;

use App\Application\Port\VoiceEventPublisherPort;
use App\Domain\Event\VoiceDomainEvent;
use App\Infrastructure\Persistence\DoctrineVoiceEventStore;

final readonly class RecordWorkerEvent
{
    public function __construct(
        private DoctrineVoiceEventStore $eventStore,
        private VoiceEventPublisherPort $publisher,
    ) {
    }

    public function execute(VoiceDomainEvent $event): void
    {
        $this->eventStore->record($event);
        $this->publisher->publish($event);
    }
}
