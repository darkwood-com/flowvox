<?php

declare(strict_types=1);

namespace App\Infrastructure\Navi;

use Navi\Domain\Execution\ActionName;
use Navi\Domain\Execution\Context;

final readonly class LogVoiceEventAction extends AbstractNdjsonLogAction
{
    public function __construct(string $logFile)
    {
        parent::__construct($logFile);
    }

    public function name(): ActionName
    {
        return ActionName::fromString('flowvox.log_voice_event');
    }

    protected function buildRecord(Context $context): array
    {
        $payload = $context->get('payload', []);
        if (!\is_array($payload)) {
            $payload = [];
        }

        $eventType = (string) $context->get('event_type', 'unknown');

        return $this->baseRecord(
            (string) $context->get('session_id', ''),
            $eventType,
            (string) $context->get('occurred_at', ''),
            $this->slimPayload($eventType, $payload),
        );
    }
}
