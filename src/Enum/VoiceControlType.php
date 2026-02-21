<?php

declare(strict_types=1);

namespace App\Enum;

enum VoiceControlType: string
{
    case START = 'START';
    case STOP = 'STOP';
}
