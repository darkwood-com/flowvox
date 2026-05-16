<?php

declare(strict_types=1);

namespace App\Application\UseCase;

final class ApplyFillerRemoval
{
    private const FILLER_PATTERN = '/\b(euh|euuh|hum|hm|uh|um|ah|ben|genre|enfin)\b/ui';

    public function execute(string $text): string
    {
        $cleaned = preg_replace(self::FILLER_PATTERN, '', $text) ?? $text;

        return trim(preg_replace('/\s{2,}/u', ' ', $cleaned) ?? $cleaned);
    }
}
