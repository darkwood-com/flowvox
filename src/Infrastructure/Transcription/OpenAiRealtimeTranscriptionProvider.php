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
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class OpenAiRealtimeTranscriptionProvider implements TranscriptionProviderInterface
{
    public function __construct(
        #[Autowire('%env(default:openai_key_empty:OPENAI_API_KEY)%')]
        private string $apiKey,
        #[Autowire('%env(default:openai_realtime_transcription_model_default:OPENAI_REALTIME_TRANSCRIPTION_MODEL)%')]
        private string $transcriptionModel,
        private RecordWorkerEvent $recordWorkerEvent,
        private LoggerInterface $logger,
    ) {
    }

    public function supports(TranscriptionProviderType $type): bool
    {
        return $type === TranscriptionProviderType::OpenAiRealtimeWhisper;
    }

    public function transcribeFile(string $wavPath, TranscriptionContext $context): TranscriptionResult
    {
        throw new \BadMethodCallException('openai_realtime_whisper transcribes during recording; use START/STOP in the worker.');
    }

    public function openRealtimeSession(RealtimeSessionConfig $config): RealtimeSessionInterface
    {
        if ($this->apiKey === '') {
            throw new \RuntimeException('OPENAI_API_KEY is required for openai_realtime_whisper.');
        }

        return new OpenAiRealtimeTranscriptionSession(
            $this->apiKey,
            $config,
            $this->transcriptionModel,
            $this->recordWorkerEvent,
            $this->logger,
        );
    }
}
