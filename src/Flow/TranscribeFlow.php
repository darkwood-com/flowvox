<?php

declare(strict_types=1);

namespace App\Flow;

use App\Application\Transcription\TranscriptionProviderRegistry;
use App\IpStrategy\WhisperTranscribeIpStrategy;
use App\Model\RecordingFinished;
use App\Model\TranscriptionChunk;
use App\Service\WorkerEventEmitter;
use Flow\DriverInterface;
use Flow\Flow\Flow;
use Psr\Log\LoggerInterface;

/**
 * Input: RecordingFinished (from RecorderFlow).
 * Output: TranscriptionChunk (after configured provider transcribes the WAV).
 *
 * @extends Flow<RecordingFinished, TranscriptionChunk>
 */
final class TranscribeFlow extends Flow
{
    public function __construct(
        ?DriverInterface $driver,
        TranscriptionProviderRegistry $providerRegistry,
        string $sessionId,
        LoggerInterface $logger,
        string $defaultLanguage = 'fr',
        ?WorkerEventEmitter $eventEmitter = null,
    ) {
        $ipStrategy = new WhisperTranscribeIpStrategy($providerRegistry, $sessionId, $logger, $defaultLanguage, $eventEmitter);

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
