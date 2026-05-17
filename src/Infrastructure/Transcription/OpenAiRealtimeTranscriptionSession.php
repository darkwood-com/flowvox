<?php

declare(strict_types=1);

namespace App\Infrastructure\Transcription;

use App\Application\Transcription\RealtimeSessionConfig;
use App\Application\Transcription\RealtimeSessionInterface;
use App\Application\UseCase\RecordWorkerEvent;
use App\Domain\Enum\VoiceDomainEventType;
use App\Domain\Event\VoiceDomainEvent;
use Psr\Log\LoggerInterface;
use WebSocket\Client;
use WebSocket\ConnectionException;

/**
 * OpenAI Realtime API transcription session (GA).
 *
 * @see https://developers.openai.com/api/docs/guides/realtime-transcription
 */
final class OpenAiRealtimeTranscriptionSession implements RealtimeSessionInterface
{
    private const WEBSOCKET_URL = 'wss://api.openai.com/v1/realtime?intent=transcription';

    /** Models that reject turn_detection in session.update (built-in VAD). */
    private const MODELS_WITHOUT_TURN_DETECTION_CONFIG = [
        'gpt-realtime-whisper',
    ];

    private readonly Client $client;

    private string $previewText = '';

    private string $finalTranscript = '';

    public function __construct(
        string $apiKey,
        private readonly RealtimeSessionConfig $config,
        private readonly string $transcriptionModel,
        private readonly RecordWorkerEvent $recordWorkerEvent,
        private readonly LoggerInterface $logger,
    ) {
        $this->client = new Client(
            self::WEBSOCKET_URL,
            [
                'timeout' => 5,
                'headers' => [
                    'Authorization' => 'Bearer ' . $apiKey,
                ],
            ],
        );

        $this->sendSessionUpdate();
        $this->drainIncoming(5);
    }

    public function sendAudioChunk(string $pcmBase64): void
    {
        if ($pcmBase64 === '') {
            return;
        }

        $this->client->text(json_encode([
            'type' => 'input_audio_buffer.append',
            'audio' => $pcmBase64,
        ], \JSON_THROW_ON_ERROR));
    }

    public function poll(): void
    {
        $this->drainIncoming(8);
    }

    /**
     * Read up to $maxMessages server events (socket timeout is whole seconds in textalk/websocket).
     */
    private function drainIncoming(int $maxMessages = 1): void
    {
        $this->client->setTimeout(1);

        for ($i = 0; $i < $maxMessages; ++$i) {
            try {
                $payload = $this->client->receive();
            } catch (ConnectionException) {
                break;
            }

            if (!\is_string($payload) || $payload === '') {
                break;
            }

            $this->handleServerEvent($payload);
        }
    }

    public function getAccumulatedTranscript(): string
    {
        return trim($this->finalTranscript !== '' ? $this->finalTranscript : $this->previewText);
    }

    public function close(): void
    {
        try {
            // Server VAD commits automatically; do not call input_audio_buffer.commit (empty buffer error).
            $this->drainIncoming(15);
        } catch (\Throwable $e) {
            $this->logger->debug('OpenAI realtime close: {message}', ['message' => $e->getMessage()]);
        }

        try {
            $this->client->close();
        } catch (\Throwable) {
        }
    }

    private function sendSessionUpdate(): void
    {
        $language = $this->config->language ?? 'fr';

        $input = [
            'format' => [
                'type' => 'audio/pcm',
                'rate' => 24000,
            ],
            'transcription' => [
                'model' => $this->transcriptionModel,
                'language' => $language,
            ],
        ];

        if (!$this->modelRejectsTurnDetectionConfig()) {
            $input['turn_detection'] = [
                'type' => 'server_vad',
                'threshold' => 0.5,
                'prefix_padding_ms' => 300,
                'silence_duration_ms' => 500,
            ];
        }

        $this->client->text(json_encode([
            'type' => 'session.update',
            'session' => [
                'type' => 'transcription',
                'audio' => [
                    'input' => $input,
                ],
            ],
        ], \JSON_THROW_ON_ERROR));
    }

    private function modelRejectsTurnDetectionConfig(): bool
    {
        return \in_array($this->transcriptionModel, self::MODELS_WITHOUT_TURN_DETECTION_CONFIG, true);
    }

    private function handleServerEvent(string $payload): void
    {
        $data = json_decode($payload, true);
        if (!\is_array($data)) {
            return;
        }

        $type = $data['type'] ?? '';

        if ($type === 'error') {
            $message = (string) ($data['error']['message'] ?? json_encode($data['error'] ?? $data));
            if ($this->isBenignError($message)) {
                $this->logger->debug('OpenAI realtime: {message}', ['message' => $message]);

                return;
            }
            $this->logger->error('OpenAI realtime error: {message}', ['message' => $message]);
            $this->publish(VoiceDomainEventType::Error, ['message' => $message]);

            return;
        }

        if (
            $type === 'conversation.item.input_audio_transcription.delta'
            || $type === 'transcript.text.delta'
        ) {
            $delta = (string) ($data['delta'] ?? '');
            if ($delta === '') {
                return;
            }
            $this->previewText .= $delta;
            $this->publish(VoiceDomainEventType::TranscriptionPartial, ['text' => $this->previewText]);

            return;
        }

        if (
            $type === 'conversation.item.input_audio_transcription.completed'
            || $type === 'conversation.item.input_audio_transcription.segment'
            || $type === 'transcript.text.done'
        ) {
            $transcript = trim((string) ($data['transcript'] ?? $data['text'] ?? ''));
            if ($transcript === '') {
                return;
            }
            $this->finalTranscript = trim($this->finalTranscript . ' ' . $transcript);
            $this->previewText = '';
            $this->publish(VoiceDomainEventType::TranscriptionPartial, ['text' => $transcript]);
            $this->publish(VoiceDomainEventType::TranscriptionFinal, ['text' => $transcript]);

            return;
        }

        if ($type === 'conversation.item.input_audio_transcription.failed') {
            $message = (string) ($data['error']['message'] ?? 'Transcription failed');
            $this->logger->warning('OpenAI realtime transcription failed: {message}', ['message' => $message]);
            $this->publish(VoiceDomainEventType::Error, ['message' => $message]);

            return;
        }

        if (\in_array($type, [
            'session.created',
            'session.updated',
            'input_audio_buffer.speech_started',
            'input_audio_buffer.speech_stopped',
            'input_audio_buffer.committed',
            'conversation.item.added',
            'conversation.item.done',
        ], true)) {
            $this->logger->debug('OpenAI realtime event: {type}', ['type' => $type]);

            return;
        }

        $this->logger->debug('OpenAI realtime event: {type}', ['type' => $type]);
    }

    private function isBenignError(string $message): bool
    {
        return str_contains($message, 'buffer too small')
            || str_contains($message, 'Turn detection is not supported');
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function publish(VoiceDomainEventType $type, array $payload): void
    {
        $this->recordWorkerEvent->execute(new VoiceDomainEvent(
            $this->config->sessionId,
            $type,
            new \DateTimeImmutable(),
            $payload,
        ));
    }
}
