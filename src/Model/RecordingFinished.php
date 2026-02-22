<?php

declare(strict_types=1);

namespace App\Model;

/**
 * Emitted when a recording is finalized (stop completed). Carries the WAV file path.
 */
final readonly class RecordingFinished
{
    public function __construct(
        public string $wavPath,
        public \DateTimeImmutable $at,
    ) {
    }
}
