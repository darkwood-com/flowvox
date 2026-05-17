<?php

declare(strict_types=1);

namespace App\Application\Transcription;

interface RealtimeSessionInterface
{
    public function sendAudioChunk(string $pcmBase64): void;

    /**
     * Drain pending WebSocket server events (non-blocking).
     */
    public function poll(): void;

    public function getAccumulatedTranscript(): string;

    public function close(): void;
}
