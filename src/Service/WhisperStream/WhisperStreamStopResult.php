<?php

declare(strict_types=1);

namespace App\Service\WhisperStream;

final readonly class WhisperStreamStopResult
{
    public function __construct(
        public string $fullText,
        public string $wavPath = '',
        public string $transcriptPath = '',
    ) {
    }
}
