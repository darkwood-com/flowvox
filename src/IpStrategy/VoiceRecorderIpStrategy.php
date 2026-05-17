<?php

declare(strict_types=1);

namespace App\IpStrategy;

use App\Domain\Enum\VoiceDomainEventType;
use App\Enum\VoiceControlType;
use App\Model\RecordingFinished;
use App\Model\VoiceControlEvent;
use App\Service\VoiceRecorder;
use App\Service\WhisperStreamRunner;
use App\Service\WorkerEventEmitter;
use Flow\Event;
use Flow\Event\PoolEvent;
use Flow\Event\PullEvent;
use Flow\Event\PushEvent;
use Flow\Ip;
use Flow\IpStrategyInterface;
use Psr\Log\LoggerInterface;

/**
 * State machine: IDLE | RECORDING | STOPPING.
 * PUSH receives VoiceControlEvent (START/STOP); POLL keeps strategy active via activeStartIp; POOL runs pollStop and emits RecordingFinished when finalized.
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
    /** @var array<int, RecordingFinished> output by spl_object_id of the START VoiceControlEvent */
    private array $outputByStartEventId = [];
    /** @var list<Ip<VoiceControlEvent>> START ip from push that produced each output */
    private array $outputQueueStartIps = [];

    public function __construct(
        private readonly VoiceRecorder $voiceRecorder,
        private readonly LoggerInterface $logger,
        private readonly bool $useWhisperStream,
        private readonly WhisperStreamRunner $whisperStreamRunner,
        private readonly string $sessionId,
        private readonly ?WorkerEventEmitter $eventEmitter = null,
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
                if ($this->useWhisperStream) {
                    $this->whisperStreamRunner->start($this->sessionId);
                    $path = 'stream://' . $this->sessionId;
                } else {
                    $path = $this->voiceRecorder->start();
                }
                $this->state = self::STATE_RECORDING;
                $this->activeStartIp = $ip;
                $this->logger->info('PUSH START -> start recording path={path}', ['path' => $path, 'stream' => $this->useWhisperStream]);
                $this->eventEmitter?->emit(VoiceDomainEventType::RecordingStarted, ['wavPath' => $path]);
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
                if ($this->useWhisperStream) {
                    $this->state = self::STATE_STOPPING;
                    $this->logger->info('PUSH STOP -> stopping whisper-stream');
                } else {
                    $this->voiceRecorder->requestStop();
                    $this->state = self::STATE_STOPPING;
                    $this->logger->info('PUSH STOP -> request stop');
                }
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

    public function getRecordingFinishedForStartEvent(VoiceControlEvent $event): RecordingFinished
    {
        $id = spl_object_id($event);
        $recording = $this->outputByStartEventId[$id] ?? null;
        if ($recording === null) {
            throw new \OutOfBoundsException(sprintf('No RecordingFinished found for VoiceControlEvent instance %d', $id));
        }
        unset($this->outputByStartEventId[$id]);

        return $recording;
    }

    public function pool(PoolEvent $event): void
    {
        if ($this->state === self::STATE_RECORDING && $this->useWhisperStream) {
            $poll = $this->whisperStreamRunner->poll();
            foreach ($poll->newPartials as $partial) {
                $this->eventEmitter?->emit(VoiceDomainEventType::TranscriptionPartial, ['text' => $partial]);
            }
        }

        if ($this->state === self::STATE_STOPPING) {
            if ($this->useWhisperStream) {
                $stopResult = $this->whisperStreamRunner->stop();
                $wavPath = $stopResult->wavPath !== '' ? $stopResult->wavPath : ('stream://' . $this->sessionId);
                $recording = new RecordingFinished($wavPath, new \DateTimeImmutable(), $stopResult->fullText);
                $this->logger->info('whisper-stream stopped wav={path}', ['path' => $wavPath]);
                $this->eventEmitter?->emit(VoiceDomainEventType::RecordingStopped, ['wavPath' => $wavPath]);
                $this->finalizeRecording($recording);
            } else {
                $wavPath = $this->voiceRecorder->pollStop();
                if ($wavPath !== null) {
                    $recording = new RecordingFinished($wavPath, new \DateTimeImmutable());
                    $this->logger->info('Recorder finished wav={path} -> emitted RecordingFinished', ['path' => $wavPath]);
                    $this->eventEmitter?->emit(VoiceDomainEventType::RecordingStopped, ['wavPath' => $wavPath]);
                    $this->finalizeRecording($recording);
                }
            }
        }

        if ($this->state !== self::STATE_IDLE && $this->activeStartIp !== null) {
            $event->addIps([$this->activeStartIp]);
        }
        $event->addIps($this->outputQueueStartIps);
    }

    private function finalizeRecording(RecordingFinished $recording): void
    {
        $this->state = self::STATE_IDLE;
        $this->outputByStartEventId[spl_object_id($this->activeStartIp->data)] = $recording;
        $this->outputQueueStartIps[] = $this->activeStartIp;
        $this->activeStartIp = null;

        if ($this->queuedStartIp !== null) {
            if ($this->useWhisperStream) {
                $this->whisperStreamRunner->start($this->sessionId);
            } else {
                $this->voiceRecorder->start();
            }
            $this->state = self::STATE_RECORDING;
            $this->activeStartIp = $this->queuedStartIp;
            $this->queuedStartIp = null;
            $this->logger->info('queued START detected -> restart recording');
        }
    }
}
