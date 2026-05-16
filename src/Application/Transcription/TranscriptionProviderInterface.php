<?php

declare(strict_types=1);

namespace App\Application\Transcription;

use App\Domain\Enum\TranscriptionProviderType;

interface TranscriptionProviderInterface
{
    public function supports(TranscriptionProviderType $type): bool;

    public function transcribeFile(string $wavPath, TranscriptionContext $context): TranscriptionResult;

    public function openRealtimeSession(RealtimeSessionConfig $config): RealtimeSessionInterface;
}
