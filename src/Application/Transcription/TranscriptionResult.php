<?php

declare(strict_types=1);

namespace App\Application\Transcription;

final readonly class TranscriptionResult
{
    public function __construct(
        public string $text,
        public ?string $language = null,
    ) {
    }
}
