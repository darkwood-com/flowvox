<?php

declare(strict_types=1);

namespace App\IpStrategy;

use App\Message\VoiceControlMessage;
use Flow\Event;
use Flow\Event\PoolEvent;
use Flow\Event\PullEvent;
use Flow\Event\PushEvent;
use Flow\Ip;
use Flow\IpPool;
use Flow\IpStrategyInterface;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;

/**
 * @template T
 *
 * @implements IpStrategyInterface<T>
 */
class VoiceTransportIpStrategy implements IpStrategyInterface
{
    private ?int $pullInterval = null;

    private ?Ip $ip = null;

    private ?float $lastPullAt = null;

    public function __construct(
        private ReceiverInterface $receiver
    )
    {
    }

    public function tick(int $pullInterval) {
        $this->pullInterval = $pullInterval;
        $this->ip = new Ip();

        return function() {
            $this->pullInterval = null;
            $this->ip = null;
        };
    }

    public static function getSubscribedEvents(): array
    {
        return [
            Event::PULL => 'pull',
            Event::POOL => 'pool',
        ];
    }

    /**
     * @param PullEvent<T> $event
     */
    public function pull(PullEvent $event): void
    {
        if($this->pullInterval === null) {
            return;
        }

        $now = microtime(true);
        if ($this->lastPullAt !== null && ($now - $this->lastPullAt) < $this->pullInterval) {
            return;
        }
        $this->lastPullAt = $now;

        $envelopes = $this->receiver->get();
        foreach ($envelopes as $envelope) {
            $message = $envelope->getMessage();
            if (!$message instanceof VoiceControlMessage) {
                $this->receiver->reject($envelope);
                return;
            }

            $this->receiver->ack($envelope);
            $event->addIp(new Ip($envelope->getMessage()));
        }
    }

    public function pool(PoolEvent $event): void
    {
        if($this->ip !== null) {
            $event->addIps([$this->ip]);
        }
    }
}
