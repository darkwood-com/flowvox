<?php

declare(strict_types=1);

namespace App\IpStrategy;

use App\Application\Transcription\TranscriptionContext;
use App\Application\Transcription\TranscriptionProviderRegistry;
use App\Domain\Enum\TranscriptionProviderType;
use App\Domain\Enum\VoiceDomainEventType;
use App\Model\RecordingFinished;
use App\Model\TranscriptionChunk;
use App\Service\WorkerEventEmitter;
use Flow\Event;
use Flow\Event\PoolEvent;
use Flow\Event\PullEvent;
use Flow\Event\PushEvent;
use Flow\Ip;
use Flow\IpStrategyInterface;
use Psr\Log\LoggerInterface;

/**
 * PUSH receives RecordingFinished; POOL runs transcription via configured provider.
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
        private readonly TranscriptionProviderRegistry $providerRegistry,
        private readonly string $sessionId,
        private readonly LoggerInterface $logger,
        private readonly string $defaultLanguage = 'fr',
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
            $liveTranscript = trim($data->liveTranscript ?? '');
            $providerType = $this->providerRegistry->getDefaultType();
            $this->logger->info('Transcribe started wav={path} provider={provider} live={live}', [
                'path' => $wavPath,
                'provider' => $providerType->value,
                'live' => $liveTranscript !== '',
            ]);

            $useLiveOnly = $liveTranscript !== ''
                && \in_array($providerType, [
                    TranscriptionProviderType::WhisperCppStream,
                    TranscriptionProviderType::OpenAiRealtimeWhisper,
                ], true);

            if ($useLiveOnly) {
                $text = $liveTranscript;
                $language = null;
            } else {
                $this->eventEmitter?->emit(VoiceDomainEventType::TranscriptionPartial, ['text' => '', 'status' => 'transcribing']);

                try {
                    if ($this->isTranscribableWav($wavPath)) {
                        $provider = $this->providerRegistry->get($providerType);
                        $result = $provider->transcribeFile($wavPath, new TranscriptionContext(
                            $this->sessionId,
                            $providerType,
                            $this->defaultLanguage,
                        ));
                        $text = $result->text;
                        $language = $result->language;
                    } elseif ($liveTranscript !== '') {
                        $text = $liveTranscript;
                        $language = null;
                        $this->logger->warning('No usable WAV for {provider}, using live stream transcript', [
                            'provider' => $providerType->value,
                        ]);
                    } else {
                        throw new \RuntimeException(sprintf(
                            'No WAV file for %s transcription (path: %s).',
                            $providerType->value,
                            $wavPath !== '' ? $wavPath : '(empty)',
                        ));
                    }
                } catch (\Throwable $e) {
                    $this->eventEmitter?->emit(VoiceDomainEventType::Error, ['message' => $e->getMessage()]);
                    $this->pending = null;

                    return;
                }
            }

            $at = new \DateTimeImmutable();
            $chunk = new TranscriptionChunk($text, $at, $wavPath);

            $this->outputChunk = $chunk;
            $this->outputInputIp = $this->pending;
            $this->pending = null;

            $this->eventEmitter?->emit(VoiceDomainEventType::TranscriptionFinal, [
                'text' => $text,
                'wavPath' => $wavPath,
                'language' => $language,
            ]);

            $preview = mb_strlen($text) > self::PREVIEW_LEN
                ? mb_substr($text, 0, self::PREVIEW_LEN) . '…'
                : $text;
            $this->logger->info('Transcribe done: {preview}', ['preview' => $preview]);
        }

        if ($this->outputInputIp !== null) {
            $event->addIps([$this->outputInputIp]);
        }
    }

    public function getTranscriptionChunk(): TranscriptionChunk
    {
        if ($this->outputChunk === null) {
            throw new \LogicException('No TranscriptionChunk available');
        }
        $chunk = $this->outputChunk;
        $this->outputChunk = null;

        return $chunk;
    }

    private function isTranscribableWav(string $wavPath): bool
    {
        if ($wavPath === '' || str_contains($wavPath, '://')) {
            return false;
        }

        if (!is_file($wavPath) || !is_readable($wavPath)) {
            return false;
        }

        $size = filesize($wavPath);

        return $size !== false && $size >= 1000;
    }
}
