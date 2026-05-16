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
 * Skeleton for GPT Realtime 2 voice agents (tools, preamble) — extend in production.
 */
final readonly class OpenAiRealtimeAgentProvider implements TranscriptionProviderInterface
{
    public function supports(TranscriptionProviderType $type): bool
    {
        return $type === TranscriptionProviderType::OpenAiRealtimeAgent;
    }

    public function transcribeFile(string $wavPath, TranscriptionContext $context): TranscriptionResult
    {
        throw new \BadMethodCallException('Agent provider requires an active realtime session, not batch WAV.');
    }

    public function openRealtimeSession(RealtimeSessionConfig $config): RealtimeSessionInterface
    {
        return new UnsupportedRealtimeSession();
    }
}
