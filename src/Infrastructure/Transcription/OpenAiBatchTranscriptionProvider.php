<?php

declare(strict_types=1);

namespace App\Infrastructure\Transcription;

use App\Application\Transcription\RealtimeSessionConfig;
use App\Application\Transcription\RealtimeSessionInterface;
use App\Application\Transcription\TranscriptionContext;
use App\Application\Transcription\TranscriptionProviderInterface;
use App\Application\Transcription\TranscriptionResult;
use App\Domain\Enum\TranscriptionProviderType;
use Symfony\AI\Platform\Message\Content\Audio;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\AI\Platform\PlatformInterface;

final readonly class OpenAiBatchTranscriptionProvider implements TranscriptionProviderInterface
{
    public function __construct(
        private PlatformInterface $platform,
    ) {
    }

    public function supports(TranscriptionProviderType $type): bool
    {
        return $type === TranscriptionProviderType::OpenAiBatch;
    }

    public function transcribeFile(string $wavPath, TranscriptionContext $context): TranscriptionResult
    {
        $binary = file_get_contents($wavPath);
        if ($binary === false) {
            throw new \RuntimeException(sprintf('Cannot read WAV file: %s', $wavPath));
        }

        $text = trim($this->platform->invoke(
            'whisper-1',
            new UserMessage(new Audio($binary, 'wav', $wavPath)),
        )->asText());

        return new TranscriptionResult($text, $context->language);
    }

    public function openRealtimeSession(RealtimeSessionConfig $config): RealtimeSessionInterface
    {
        throw new \BadMethodCallException('Use openai_realtime_whisper for streaming transcription.');
    }
}
