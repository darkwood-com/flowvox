<?php

declare(strict_types=1);

namespace App\Flow;

use App\Application\Transcription\TranscriptionProviderRegistry;
use App\Infrastructure\Transcription\OpenAiRealtimeTranscriptionProvider;
use App\IpStrategy\VoiceRecorderIpStrategy;
use App\Model\RecordingFinished;
use App\Model\VoiceControlEvent;
use App\Service\VoiceRecorder;
use App\Service\WhisperStreamRunner;
use App\Service\WorkerEventEmitter;
use Flow\DriverInterface;
use Flow\Flow\Flow;
use Psr\Log\LoggerInterface;

/**
 * Input: VoiceControlEvent (from InputProviderFlow).
 * Output: RecordingFinished (emitted when a recording is finalized after stop).
 *
 * @extends Flow<VoiceControlEvent, RecordingFinished>
 */
final class RecorderFlow extends Flow
{
    public function __construct(
        ?DriverInterface $driver,
        VoiceRecorder $voiceRecorder,
        LoggerInterface $logger,
        bool $useWhisperStream,
        WhisperStreamRunner $whisperStreamRunner,
        TranscriptionProviderRegistry $providerRegistry,
        OpenAiRealtimeTranscriptionProvider $openAiRealtimeProvider,
        string $sessionId,
        string $defaultLanguage,
        ?WorkerEventEmitter $eventEmitter = null,
    ) {
        $ipStrategy = new VoiceRecorderIpStrategy(
            $voiceRecorder,
            $logger,
            $useWhisperStream,
            $whisperStreamRunner,
            $providerRegistry,
            $openAiRealtimeProvider,
            $sessionId,
            $defaultLanguage,
            $eventEmitter,
        );

        parent::__construct(
            function (VoiceControlEvent $data) use ($ipStrategy): RecordingFinished {
                return $ipStrategy->getRecordingFinishedForStartEvent($data);
            },
            null,
            $ipStrategy,
            null,
            null,
            $driver,
        );
    }
}
