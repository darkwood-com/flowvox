<?php

declare(strict_types=1);

namespace App\IpStrategy;

use App\Model\RecordingFinished;
use App\Model\TranscriptionChunk;
use App\Service\WhisperCpp;
use Flow\Event;
use Flow\Event\PoolEvent;
use Flow\Event\PullEvent;
use Flow\Event\PushEvent;
use Flow\Ip;
use Flow\IpStrategyInterface;
use Psr\Log\LoggerInterface;

/**
 * PUSH receives RecordingFinished (recorder output); POLL returns input IP when transcription is done; POOL runs Whisper and stores TranscriptionChunk.
 *
 * @implements IpStrategyInterface<RecordingFinished>
 */
final class WhisperTranscribeIpStrategy implements IpStrategyInterface
{
    private const PREVIEW_LEN = 120;

    private ?Ip $pending = null;
    private ?TranscriptionChunk $outputChunk = null;
    private ?Ip $outputInputIp = null;

    public function __construct(
        private readonly WhisperCpp $whisperCpp,
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
        if (!$data instanceof RecordingFinished) {
            return;
        }
        $this->pending = $ip;
        $this->logger->debug('TranscribeFlow PUSH received RecordingFinished wav={path}', ['path' => $data->wavPath]);
    }

    public function pull(PullEvent $event): void
    {
        if ($this->outputInputIp === null) {
            return;
        }
        $event->addIp($this->outputInputIp);
        $this->outputInputIp = null;
    }

    public function pool(PoolEvent $event): void
    {
        if ($this->pending !== null) {
            $event->addIps([$this->pending]);

            $data = $this->pending->data;
            if (!$data instanceof RecordingFinished) {
                $this->pending = null;
                return;
            }

            $wavPath = $data->wavPath;
            $this->logger->info('Transcribe started wav={path}', ['path' => $wavPath]);

            $text = $this->whisperCpp->transcribe($wavPath);
            $at = new \DateTimeImmutable();
            $chunk = new TranscriptionChunk($text, $at, $wavPath);

            $this->outputChunk = $chunk;
            $this->outputInputIp = $this->pending;
            $this->pending = null;

            $preview = mb_strlen($text) > self::PREVIEW_LEN
                ? mb_substr($text, 0, self::PREVIEW_LEN) . '…'
                : $text;
            $this->logger->info('Transcribe done: {preview}', ['preview' => $preview]);
        }

        if ($this->outputInputIp !== null) {
            $event->addIps([$this->outputInputIp]);
        }
    }

    /**
     * Returns the TranscriptionChunk for the last completed transcription (called by the flow job).
     * MVP: one output at a time. Clears stored chunk after retrieval.
     */
    public function getTranscriptionChunk(): TranscriptionChunk
    {
        if ($this->outputChunk === null) {
            throw new \LogicException('No TranscriptionChunk available');
        }
        $chunk = $this->outputChunk;
        $this->outputChunk = null;
        return $chunk;
    }
}
