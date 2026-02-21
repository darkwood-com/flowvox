<?php

declare(strict_types=1);

namespace App\Model;

use App\Enum\VoiceControlType;

final readonly class VoiceControlEvent
{
    public function __construct(
        public VoiceControlType $type,
        public \DateTimeImmutable $at,
    ) {
    }
}
