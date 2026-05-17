<?php

declare(strict_types=1);

namespace App\Service\WhisperStream;

final readonly class WhisperStreamPollResult
{
    /**
     * @param list<string> $newPartials New text segments since last poll
     */
    public function __construct(
        public array $newPartials = [],
        public string $accumulatedText = '',
    ) {
    }
}
