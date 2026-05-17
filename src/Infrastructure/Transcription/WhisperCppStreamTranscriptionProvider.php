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
use App\Service\WhisperStreamRunner;

final readonly class WhisperCppStreamTranscriptionProvider implements TranscriptionProviderInterface
{
    public function __construct(
        private WhisperStreamRunner $whisperStreamRunner,
        private WhisperCpp $whisperCpp,
    ) {
    }

    public function supports(TranscriptionProviderType $type): bool
    {
        return $type === TranscriptionProviderType::WhisperCppStream;
    }

    public function transcribeFile(string $wavPath, TranscriptionContext $context): TranscriptionResult
    {
        if ($wavPath !== '' && is_file($wavPath)) {
            return new TranscriptionResult($this->whisperCpp->transcribe($wavPath));
        }

        return new TranscriptionResult('');
    }

    public function openRealtimeSession(RealtimeSessionConfig $config): RealtimeSessionInterface
    {
        if (!$this->whisperStreamRunner->isRunning()) {
            $this->whisperStreamRunner->start($config->sessionId);
        }

        return new WhisperStreamRealtimeSession($this->whisperStreamRunner);
    }
}
