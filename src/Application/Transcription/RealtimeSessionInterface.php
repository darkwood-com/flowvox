<?php

declare(strict_types=1);

namespace App\Application\Transcription;

interface RealtimeSessionInterface
{
    public function sendAudioChunk(string $pcmBase64): void;

    public function close(): void;
}
