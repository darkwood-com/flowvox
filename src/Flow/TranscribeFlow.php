<?php

declare(strict_types=1);

namespace App\Flow;

use App\IpStrategy\WhisperTranscribeIpStrategy;
use App\Model\RecordingFinished;
use App\Model\TranscriptionChunk;
use App\Service\WhisperCpp;
use Flow\DriverInterface;
use Flow\Flow\Flow;
use Psr\Log\LoggerInterface;

/**
 * Input: RecordingFinished (from RecorderFlow).
 * Output: TranscriptionChunk (after Whisper transcribes the WAV).
 *
 * @extends Flow<RecordingFinished, TranscriptionChunk>
 */
final class TranscribeFlow extends Flow
{
    public function __construct(
        ?DriverInterface $driver,
        WhisperCpp $whisperCpp,
        LoggerInterface $logger,
    ) {
        $ipStrategy = new WhisperTranscribeIpStrategy($whisperCpp, $logger);

        parent::__construct(
            function (RecordingFinished $data) use ($ipStrategy): TranscriptionChunk {
                return $ipStrategy->getTranscriptionChunk();
            },
            null,
            $ipStrategy,
            null,
            null,
            $driver,
        );
    }
}
