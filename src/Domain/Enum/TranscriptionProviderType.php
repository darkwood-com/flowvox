<?php

declare(strict_types=1);

namespace App\Domain\Enum;

enum TranscriptionProviderType: string
{
    case WhisperCpp = 'whisper_cpp';
    case WhisperCppStream = 'whisper_cpp_stream';
    case OpenAiBatch = 'openai_batch';
    case OpenAiRealtimeWhisper = 'openai_realtime_whisper';
    case OpenAiRealtimeAgent = 'openai_realtime_agent';
    case OpenAiRealtimeTranslate = 'openai_realtime_translate';
}
