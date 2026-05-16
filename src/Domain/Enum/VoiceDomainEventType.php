<?php

declare(strict_types=1);

namespace App\Domain\Enum;

enum VoiceDomainEventType: string
{
    case Heartbeat = 'heartbeat';
    case RecordingStarted = 'recording_started';
    case RecordingStopped = 'recording_stopped';
    case TranscriptionPartial = 'transcription_partial';
    case TranscriptionFinal = 'transcription_final';
    case Error = 'error';
}
