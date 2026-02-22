<?php

declare(strict_types=1);

namespace App\Flow;

use App\IpStrategy\VoiceRecorderIpStrategy;
use App\Model\RecordingFinished;
use App\Model\VoiceControlEvent;
use App\Service\VoiceRecorder;
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
    ) {
        $ipStrategy = new VoiceRecorderIpStrategy($voiceRecorder, $logger);

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
