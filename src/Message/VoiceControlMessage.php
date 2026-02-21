<?php

declare(strict_types=1);

namespace App\Message;

use App\Enum\VoiceControlType;

final readonly class VoiceControlMessage
{
    public function __construct(
        public VoiceControlType $type,
        public \DateTimeImmutable $at,
    ) {
    }
}
