<?php

declare(strict_types=1);

namespace App\Service;

use App\Application\UseCase\RecordWorkerEvent;
use App\Domain\Enum\VoiceDomainEventType;
use App\Domain\Event\VoiceDomainEvent;

/**
 * Emits domain events from the voice worker process (one instance per worker).
 */
final class WorkerEventEmitter
{
    private string $sessionId = '';

    public function __construct(
        private readonly RecordWorkerEvent $recordWorkerEvent,
    ) {
    }

    public function bindSession(string $sessionId): void
    {
        $this->sessionId = $sessionId;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function emit(VoiceDomainEventType $type, array $payload = []): void
    {
        if ($this->sessionId === '') {
            return;
        }

        $this->recordWorkerEvent->execute(new VoiceDomainEvent(
            $this->sessionId,
            $type,
            new \DateTimeImmutable(),
            $payload,
        ));
    }
}
