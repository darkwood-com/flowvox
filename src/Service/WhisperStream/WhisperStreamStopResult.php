<?php

declare(strict_types=1);

namespace App\Service\WhisperStream;

final readonly class WhisperStreamStopResult
{
    /**
     * @param list<string> $finalPartials segments parsed while stopping (stdout flush)
     */
    public function __construct(
        public string $fullText,
        public string $wavPath = '',
        public string $transcriptPath = '',
        public array $finalPartials = [],
    ) {
    }
}
