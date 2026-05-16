<?php

declare(strict_types=1);

namespace App\Application\Transcription;

use App\Domain\Enum\TranscriptionProviderType;

final readonly class TranscriptionContext
{
    public function __construct(
        public string $sessionId,
        public TranscriptionProviderType $provider,
        public ?string $language = null,
        public ?string $recordingId = null,
    ) {
    }
}
