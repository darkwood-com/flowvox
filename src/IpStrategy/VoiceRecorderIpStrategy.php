<?php

declare(strict_types=1);

namespace App\IpStrategy;

use App\Enum\VoiceControlType;
use App\Model\AudioChunk;
use App\Model\VoiceControlEvent;
use App\Service\VoiceRecorder;
use Flow\Event;
use Flow\Event\PoolEvent;
use Flow\Event\PullEvent;
use Flow\Event\PushEvent;
use Flow\Ip;
use Flow\IpStrategyInterface;
use Psr\Log\LoggerInterface;

/**
 * State machine: IDLE | RECORDING | STOPPING.
 * PUSH receives VoiceControlEvent (START/STOP); POLL keeps strategy active via activeStartIp; POOL runs pollStop and emits AudioChunk when finalized.
 *
 * @implements IpStrategyInterface<VoiceControlEvent>
 */
final class VoiceRecorderIpStrategy implements IpStrategyInterface
{
    private const STATE_IDLE = 'idle';
    private const STATE_RECORDING = 'recording';
    private const STATE_STOPPING = 'stopping';

    private string $state = self::STATE_IDLE;
    private ?Ip $activeStartIp = null;
    private ?Ip $queuedStartIp = null;
    /** @var array<int, AudioChunk> chunk by spl_object_id of the START VoiceControlEvent */
    private array $chunksByStartEventId = [];
    /** @var list<Ip<VoiceControlEvent>> START ip from push that produced each output */
    private array $outputQueueStartIps = [];

    public function __construct(
        private readonly VoiceRecorder $voiceRecorder,
        private readonly LoggerInterface $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            Event::PUSH => 'push',
            Event::PULL => 'pull',
            Event::POOL => 'pool',
        ];
    }

    public function push(PushEvent $event): void
    {
        $ip = $event->getIp();
        $data = $ip->data;
        if ($data->type === VoiceControlType::START) {
            if ($this->state === self::STATE_IDLE) {
                $path = $this->voiceRecorder->start();
                $this->state = self::STATE_RECORDING;
                $this->activeStartIp = $ip;
                $this->logger->info('PUSH START -> start recording path={path}', ['path' => $path]);
            } elseif ($this->state === self::STATE_RECORDING) {
                $this->logger->debug('PUSH START ignored (already recording)');
            } elseif ($this->state === self::STATE_STOPPING) {
                $this->queuedStartIp = $ip;
                $this->logger->info('PUSH START -> queued (stopping)');
            }
            return;
        }

        if ($data->type === VoiceControlType::STOP) {
            if ($this->state === self::STATE_RECORDING) {
                $this->voiceRecorder->requestStop();
                $this->state = self::STATE_STOPPING;
                $this->logger->info('PUSH STOP -> request stop');
            } elseif ($this->state === self::STATE_IDLE || $this->state === self::STATE_STOPPING) {
                $this->logger->debug('PUSH STOP ignored (state={state})', ['state' => $this->state]);
            }
        }
    }

    public function pull(PullEvent $event): void
    {
        if ($this->outputQueueStartIps === []) {
            return;
        }
        $ip = array_shift($this->outputQueueStartIps);
        $event->addIp($ip);
    }

    /**
     * Returns the AudioChunk produced for the given START VoiceControlEvent (keyed by instance id).
     * Removes the chunk from the map after retrieval.
     */
    public function getAudioChunkForStartEvent(VoiceControlEvent $event): AudioChunk
    {
        $id = spl_object_id($event);
        $chunk = $this->chunksByStartEventId[$id] ?? null;
        if ($chunk === null) {
            throw new \OutOfBoundsException(sprintf('No AudioChunk found for VoiceControlEvent instance %d', $id));
        }
        unset($this->chunksByStartEventId[$id]);

        return $chunk;
    }

    public function pool(PoolEvent $event): void
    {
        if ($this->state === self::STATE_STOPPING) {
            $wavPath = $this->voiceRecorder->pollStop();
            if ($wavPath !== null) {
                $this->logger->info('POOL -> stop finalized path={path} -> emitted AudioChunk', ['path' => $wavPath]);
                $this->state = self::STATE_IDLE;
                $this->chunksByStartEventId[spl_object_id($this->activeStartIp->data)] = new AudioChunk();
                $this->outputQueueStartIps[] = $this->activeStartIp;
                $this->activeStartIp = null;

                if ($this->queuedStartIp !== null) {
                    $this->voiceRecorder->start();
                    $this->state = self::STATE_RECORDING;
                    $this->activeStartIp = $this->queuedStartIp;
                    $this->queuedStartIp = null;
                    $this->logger->info('queued START detected -> restart recording');
                }
            }
        }

        if ($this->state !== self::STATE_IDLE && $this->activeStartIp !== null) {
            $event->addIps([$this->activeStartIp]);
        }
        $event->addIps($this->outputQueueStartIps);
    }
}
