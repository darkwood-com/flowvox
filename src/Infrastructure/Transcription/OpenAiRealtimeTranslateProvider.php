<?php

declare(strict_types=1);

namespace App\Infrastructure\Transcription;

use App\Application\Transcription\RealtimeSessionConfig;
use App\Application\Transcription\RealtimeSessionInterface;
use App\Application\Transcription\TranscriptionContext;
use App\Application\Transcription\TranscriptionProviderInterface;
use App\Application\Transcription\TranscriptionResult;
use App\Domain\Enum\TranscriptionProviderType;

/**
 * Placeholder for GPT Realtime Translate — wire to OpenAI translate model in a future release.
 */
final readonly class OpenAiRealtimeTranslateProvider implements TranscriptionProviderInterface
{
    public function supports(TranscriptionProviderType $type): bool
    {
        return $type === TranscriptionProviderType::OpenAiRealtimeTranslate;
    }

    public function transcribeFile(string $wavPath, TranscriptionContext $context): TranscriptionResult
    {
        throw new \BadMethodCallException('Translate provider is not implemented yet.');
    }

    public function openRealtimeSession(RealtimeSessionConfig $config): RealtimeSessionInterface
    {
        return new UnsupportedRealtimeSession();
    }
}
