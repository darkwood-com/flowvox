<?php

declare(strict_types=1);

namespace App\Infrastructure\Transcription;

use App\Application\Transcription\RealtimeSessionInterface;
use App\Service\WhisperStreamRunner;

/**
 * Realtime session backed by whisper-stream (SDL2 mic). Audio chunks are ignored; the mic is owned by the subprocess.
 */
final class WhisperStreamRealtimeSession implements RealtimeSessionInterface
{
    public function __construct(
        private readonly WhisperStreamRunner $runner,
    ) {
    }

    public function sendAudioChunk(string $pcmBase64): void
    {
        // whisper-stream captures audio via SDL2; browser PCM is not used.
    }

    public function close(): void
    {
        if ($this->runner->isRunning()) {
            $this->runner->stop();
        }
    }
}
