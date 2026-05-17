<?php

declare(strict_types=1);

namespace App\Service;

enum WhisperMode: string
{
    case Batch = 'batch';
    case Stream = 'stream';

    public static function fromEnv(string $value): self
    {
        return match (strtolower(trim($value))) {
            'stream' => self::Stream,
            default => self::Batch,
        };
    }
}
