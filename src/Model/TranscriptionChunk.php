<?php

declare(strict_types=1);

namespace App\Model;

/**
 * Output of transcription for one recording. Carries the transcribed text and optional wav path for debugging.
 */
final readonly class TranscriptionChunk
{
    public function __construct(
        public string $text,
        public \DateTimeImmutable $at,
        public string $wavPath = '',
    ) {
    }
}
