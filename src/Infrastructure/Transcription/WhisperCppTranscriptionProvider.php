<?php

declare(strict_types=1);

namespace App\Infrastructure\Transcription;

use App\Application\Transcription\RealtimeSessionConfig;
use App\Application\Transcription\RealtimeSessionInterface;
use App\Application\Transcription\TranscriptionContext;
use App\Application\Transcription\TranscriptionProviderInterface;
use App\Application\Transcription\TranscriptionResult;
use App\Domain\Enum\TranscriptionProviderType;
use App\Service\WhisperCpp;

final readonly class WhisperCppTranscriptionProvider implements TranscriptionProviderInterface
{
    public function __construct(
        private WhisperCpp $whisperCpp,
    ) {
    }

    public function supports(TranscriptionProviderType $type): bool
    {
        return $type === TranscriptionProviderType::WhisperCpp;
    }

    public function transcribeFile(string $wavPath, TranscriptionContext $context): TranscriptionResult
    {
        return new TranscriptionResult($this->whisperCpp->transcribe($wavPath));
    }

    public function openRealtimeSession(RealtimeSessionConfig $config): RealtimeSessionInterface
    {
        throw new \BadMethodCallException('Whisper.cpp does not support realtime sessions. Use openai_realtime_whisper.');
    }
}
