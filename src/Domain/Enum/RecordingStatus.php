<?php

declare(strict_types=1);

namespace App\Domain\Enum;

enum RecordingStatus: string
{
    case Recording = 'recording';
    case Finalizing = 'finalizing';
    case Completed = 'completed';
    case Failed = 'failed';
}
