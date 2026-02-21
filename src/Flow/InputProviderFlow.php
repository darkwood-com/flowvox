<?php

declare(strict_types=1);

namespace App\Flow;

use App\Model\VoiceControlEvent;

/**
 * First flow: pass-through. For MVP, returns the event unchanged.
 */
final readonly class InputProviderFlow
{
    public function __invoke(VoiceControlEvent $event): VoiceControlEvent
    {
        return $event;
    }
}
