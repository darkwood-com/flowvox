<?php

declare(strict_types=1);

namespace App\Domain\Enum;

enum WorkerStatus: string
{
    case Idle = 'idle';
    case Recording = 'recording';
    case Stopping = 'stopping';
    case Transcribing = 'transcribing';
    case Error = 'error';
}
