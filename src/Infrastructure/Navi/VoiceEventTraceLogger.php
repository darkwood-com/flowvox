<?php

declare(strict_types=1);

namespace App\Infrastructure\Navi;

use App\Domain\Enum\VoiceDomainEventType;
use App\Domain\Event\VoiceDomainEvent;
use Navi\Application\Workflow\WorkflowRunner;
use Navi\Domain\Execution\Context;

/**
 * Structured NDJSON audit trail for voice worker events (via darkwood/navi).
 */
final readonly class VoiceEventTraceLogger
{
    public function __construct(
        private WorkflowRunner $runner,
        private LogVoiceEventAction $logAction,
        private bool $enabled = false,
        private bool $skipHeartbeat = true,
    ) {
    }

    public function trace(VoiceDomainEvent $event): void
    {
        if (!$this->enabled) {
            return;
        }

        if ($this->skipHeartbeat && VoiceDomainEventType::Heartbeat === $event->type) {
            return;
        }

        $this->runner->run(
            Context::fromArray([
                'session_id' => $event->sessionId,
                'event_type' => $event->type->value,
                'occurred_at' => $event->occurredAt->format(\DateTimeInterface::ATOM),
                'payload' => $event->payload,
            ]),
            [$this->logAction],
        );
    }
}
