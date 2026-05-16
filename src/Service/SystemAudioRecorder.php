<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Phase 2: record system audio (meetings) on macOS via avfoundation screen/audio device.
 */
final class SystemAudioRecorder
{
    public function __construct(
        private readonly VoiceRecorder $voiceRecorder,
    ) {
    }

    public function start(): string
    {
        throw new \BadMethodCallException('System audio recording is planned for phase 2. Use voice:worker with microphone for now.');
    }
}
