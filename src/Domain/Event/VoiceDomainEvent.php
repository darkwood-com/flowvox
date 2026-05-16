<?php

declare(strict_types=1);

namespace App\Domain\Event;

use App\Domain\Enum\VoiceDomainEventType;

final readonly class VoiceDomainEvent
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public string $sessionId,
        public VoiceDomainEventType $type,
        public \DateTimeImmutable $occurredAt,
        public array $payload = [],
    ) {
    }
}
