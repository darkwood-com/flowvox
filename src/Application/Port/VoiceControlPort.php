<?php

declare(strict_types=1);

namespace App\Application\Port;

use App\Enum\VoiceControlType;

interface VoiceControlPort
{
    /**
     * @return list<string> session IDs targeted
     */
    public function send(VoiceControlType $type, ?string $sessionId = null): array;
}
