<?php

declare(strict_types=1);

namespace App\Application\Port;

use App\Domain\Event\VoiceDomainEvent;

interface VoiceEventPublisherPort
{
    public function publish(VoiceDomainEvent $event): void;
}
