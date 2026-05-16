<?php

declare(strict_types=1);

namespace App\Application\UseCase;

use App\Application\Port\VoiceControlPort;
use App\Enum\VoiceControlType;

final readonly class SendVoiceControl
{
    public function __construct(
        private VoiceControlPort $voiceControl,
    ) {
    }

    /**
     * @return list<string> session IDs that received the message
     */
    public function execute(VoiceControlType $type, ?string $sessionId = null): array
    {
        return $this->voiceControl->send($type, $sessionId);
    }
}
