<?php

declare(strict_types=1);

namespace App\Flow;

use App\IpStrategy\VoiceTransportIpStrategy;
use App\Message\VoiceControlMessage;
use App\Model\VoiceControlEvent;
use Closure;
use Flow\Flow\Flow;
use Flow\AsyncHandlerInterface;
use Flow\DriverInterface;
use Flow\ExceptionInterface;
use Flow\Ip;
use Flow\IpStrategyInterface;
use Flow\Job\YJob;
use Flow\JobInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;

/**
 * @template T1
 * @template T2
 *
 * @extends \Flow\Flow\Flow<T1,T2>
 */
class InputProviderFlow extends Flow
{
    private VoiceTransportIpStrategy $ipStrategy;

    public function __construct(
        ?DriverInterface $driver,
        ReceiverInterface $receiver,
    ) {
        $this->ipStrategy = new VoiceTransportIpStrategy($receiver);

        parent::__construct(static function(VoiceControlMessage $message) {
            return new VoiceControlEvent($message->type, $message->at);
        }, null, $this->ipStrategy, null, null, $driver);
    }

    public function tick(int $pullInterval) {
        return $this->ipStrategy->tick($pullInterval);
    }
}
