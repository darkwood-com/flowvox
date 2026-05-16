<?php

declare(strict_types=1);

namespace App\Infrastructure\Transcription;

use App\Application\Transcription\RealtimeSessionInterface;

final class UnsupportedRealtimeSession implements RealtimeSessionInterface
{
    public function sendAudioChunk(string $pcmBase64): void
    {
    }

    public function close(): void
    {
    }
}
