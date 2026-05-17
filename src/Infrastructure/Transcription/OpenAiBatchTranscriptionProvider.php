<?php

declare(strict_types=1);

namespace App\Infrastructure\Transcription;

use App\Application\Transcription\RealtimeSessionConfig;
use App\Application\Transcription\RealtimeSessionInterface;
use App\Application\Transcription\TranscriptionContext;
use App\Application\Transcription\TranscriptionProviderInterface;
use App\Application\Transcription\TranscriptionResult;
use App\Domain\Enum\TranscriptionProviderType;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * OpenAI /v1/audio/transcriptions (batch, post-STOP WAV).
 *
 * @see https://platform.openai.com/docs/guides/speech-to-text
 */
final readonly class OpenAiBatchTranscriptionProvider implements TranscriptionProviderInterface
{
    private const MIN_WAV_BYTES = 1000;

    /** @var list<string> */
    private const ALLOWED_MODELS = [
        'gpt-4o-transcribe',
        'gpt-4o-mini-transcribe',
        'gpt-4o-transcribe-diarize',
        'whisper-1',
    ];

    public function __construct(
        private HttpClientInterface $httpClient,
        #[Autowire('%env(default:openai_key_empty:OPENAI_API_KEY)%')]
        private string $apiKey,
        #[Autowire('%env(default:openai_transcription_model_default:OPENAI_TRANSCRIPTION_MODEL)%')]
        private string $model,
    ) {
    }

    public function supports(TranscriptionProviderType $type): bool
    {
        return $type === TranscriptionProviderType::OpenAiBatch;
    }

    public function transcribeFile(string $wavPath, TranscriptionContext $context): TranscriptionResult
    {
        if ($this->apiKey === '') {
            throw new \RuntimeException('OPENAI_API_KEY is required for openai_batch transcription.');
        }

        $this->assertTranscribableWav($wavPath);

        $model = $this->model;
        if (!\in_array($model, self::ALLOWED_MODELS, true)) {
            throw new \InvalidArgumentException(sprintf(
                'Unsupported OPENAI_TRANSCRIPTION_MODEL "%s". Allowed: %s',
                $model,
                implode(', ', self::ALLOWED_MODELS),
            ));
        }

        $language = $context->language ?? '';
        $body = [
            'model' => $model,
            'file' => fopen($wavPath, 'rb'),
            'response_format' => 'json',
        ];
        if ($language !== '') {
            $body['language'] = $language;
        }

        if ($model === 'gpt-4o-transcribe-diarize') {
            $body['response_format'] = 'json';
            $body['chunking_strategy'] = 'auto';
        }

        try {
            $response = $this->httpClient->request('POST', 'https://api.openai.com/v1/audio/transcriptions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                ],
                'body' => $body,
            ]);

            $status = $response->getStatusCode();
            $data = $response->toArray(false);
        } catch (\Throwable $e) {
            throw new \RuntimeException(sprintf(
                'OpenAI transcription request failed for %s: %s',
                basename($wavPath),
                $e->getMessage(),
            ), 0, $e);
        }

        if ($status >= 400) {
            $message = \is_array($data['error'] ?? null)
                ? (string) ($data['error']['message'] ?? json_encode($data['error']))
                : (string) json_encode($data);
            throw new \RuntimeException(sprintf(
                'OpenAI transcription failed (%d) for %s: %s',
                $status,
                basename($wavPath),
                $message,
            ));
        }

        $text = $this->extractText($data, $model);

        return new TranscriptionResult($text, $language !== '' ? $language : null);
    }

    public function openRealtimeSession(RealtimeSessionConfig $config): RealtimeSessionInterface
    {
        throw new \BadMethodCallException('Use openai_realtime_whisper for streaming transcription.');
    }

    /**
     * @param array<string, mixed> $data
     */
    private function extractText(array $data, string $model): string
    {
        if (isset($data['text']) && \is_string($data['text'])) {
            return trim($data['text']);
        }

        if ($model === 'gpt-4o-transcribe-diarize' && isset($data['segments']) && \is_array($data['segments'])) {
            $parts = [];
            foreach ($data['segments'] as $segment) {
                if (\is_array($segment) && isset($segment['text']) && \is_string($segment['text'])) {
                    $parts[] = trim($segment['text']);
                }
            }

            return trim(implode(' ', array_filter($parts)));
        }

        throw new \RuntimeException(sprintf(
            'OpenAI transcription response missing text field: %s',
            (string) json_encode($data),
        ));
    }

    private function assertTranscribableWav(string $wavPath): void
    {
        if ($wavPath === '' || str_contains($wavPath, '://')) {
            throw new \RuntimeException(sprintf(
                'No WAV file available for OpenAI transcription (path: %s).',
                $wavPath !== '' ? $wavPath : '(empty)',
            ));
        }

        if (!is_file($wavPath) || !is_readable($wavPath)) {
            throw new \RuntimeException(sprintf('WAV file not readable: %s', $wavPath));
        }

        $size = filesize($wavPath);
        if ($size === false || $size < self::MIN_WAV_BYTES) {
            throw new \RuntimeException(sprintf(
                'WAV file too small for OpenAI (%d bytes): %s',
                $size === false ? 0 : $size,
                $wavPath,
            ));
        }
    }
}
