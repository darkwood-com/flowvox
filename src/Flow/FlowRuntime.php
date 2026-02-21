<?php

declare(strict_types=1);

namespace App\Flow;

use App\Model\VoiceControlEvent;

final readonly class FlowRuntime
{
    public function __construct(
        private InputProviderFlow $inputProviderFlow,
    ) {
    }

    public function runInputProvider(VoiceControlEvent $event): VoiceControlEvent
    {
        return ($this->inputProviderFlow)($event);
    }
}
