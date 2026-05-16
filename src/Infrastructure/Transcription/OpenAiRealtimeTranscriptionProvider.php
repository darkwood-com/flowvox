<?php

declare(strict_types=1);

namespace App\Infrastructure\Transcription;

use App\Application\Transcription\RealtimeSessionConfig;
use App\Application\Transcription\RealtimeSessionInterface;
use App\Application\Transcription\TranscriptionContext;
use App\Application\Transcription\TranscriptionProviderInterface;
use App\Application\Transcription\TranscriptionResult;
use App\Application\UseCase\RecordWorkerEvent;
use App\Domain\Enum\TranscriptionProviderType;
use App\Domain\Enum\VoiceDomainEventType;
use App\Domain\Event\VoiceDomainEvent;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\WebSocketConnection;

/**
 * Streams transcription deltas via OpenAI Realtime API (transcription session).
 */
final readonly class OpenAiRealtimeTranscriptionProvider implements TranscriptionProviderInterface
{
    public function __construct(
        private HttpClientInterface $httpClient,
        #[Autowire('%env(default:openai_key_empty:OPENAI_API_KEY)%')]
        private string $apiKey,
        private RecordWorkerEvent $recordWorkerEvent,
    ) {
    }

    public function supports(TranscriptionProviderType $type): bool
    {
        return $type === TranscriptionProviderType::OpenAiRealtimeWhisper;
    }

    public function transcribeFile(string $wavPath, TranscriptionContext $context): TranscriptionResult
    {
        $binary = file_get_contents($wavPath);
        if ($binary === false) {
            throw new \RuntimeException(sprintf('Cannot read WAV file: %s', $wavPath));
        }

        $session = $this->openRealtimeSession(new RealtimeSessionConfig(
            $context->sessionId,
            TranscriptionProviderType::OpenAiRealtimeWhisper,
            $context->language,
        ));

        $session->sendAudioChunk(base64_encode($binary));
        $session->close();

        return new TranscriptionResult('', $context->language);
    }

    public function openRealtimeSession(RealtimeSessionConfig $config): RealtimeSessionInterface
    {
        if ($this->apiKey === '') {
            throw new \RuntimeException('OPENAI_API_KEY is required for realtime transcription.');
        }

        return new OpenAiRealtimeTranscriptionSession(
            $this->httpClient,
            $this->apiKey,
            $config,
            $this->recordWorkerEvent,
        );
    }
}

final class OpenAiRealtimeTranscriptionSession implements RealtimeSessionInterface
{
    private ?WebSocketConnection $connection = null;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $apiKey,
        private readonly RealtimeSessionConfig $config,
        private readonly RecordWorkerEvent $recordWorkerEvent,
    ) {
    }

    public function sendAudioChunk(string $pcmBase64): void
    {
        $this->ensureConnection();
        $this->connection?->send(json_encode([
            'type' => 'input_audio_buffer.append',
            'audio' => $pcmBase64,
        ], \JSON_THROW_ON_ERROR));
    }

    public function close(): void
    {
        $this->connection?->close();
        $this->connection = null;
    }

    private function ensureConnection(): void
    {
        if ($this->connection !== null) {
            return;
        }

        $response = $this->httpClient->request('GET', 'wss://api.openai.com/v1/realtime', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'OpenAI-Beta' => 'realtime=v1',
            ],
            'query' => [
                'model' => 'gpt-4o-realtime-preview',
            ],
        ]);

        $this->connection = $response->getInfo('websocket') ?? null;
        if (!$this->connection instanceof WebSocketConnection) {
            throw new \RuntimeException('WebSocket connection to OpenAI Realtime API failed.');
        }

        $this->connection->send(json_encode([
            'type' => 'session.update',
            'session' => [
                'modalities' => ['text'],
                'input_audio_transcription' => [
                    'model' => 'whisper-1',
                ],
            ],
        ], \JSON_THROW_ON_ERROR));
    }

    public function handleServerEvent(string $payload): void
    {
        $data = json_decode($payload, true);
        if (!\is_array($data)) {
            return;
        }

        $type = $data['type'] ?? '';
        if ($type === 'conversation.item.input_audio_transcription.delta') {
            $delta = (string) ($data['delta'] ?? '');
            $this->recordWorkerEvent->execute(new VoiceDomainEvent(
                $this->config->sessionId,
                VoiceDomainEventType::TranscriptionPartial,
                new \DateTimeImmutable(),
                ['text' => $delta],
            ));
        }

        if ($type === 'conversation.item.input_audio_transcription.completed') {
            $transcript = (string) ($data['transcript'] ?? '');
            $this->recordWorkerEvent->execute(new VoiceDomainEvent(
                $this->config->sessionId,
                VoiceDomainEventType::TranscriptionFinal,
                new \DateTimeImmutable(),
                ['text' => $transcript],
            ));
        }
    }
}
