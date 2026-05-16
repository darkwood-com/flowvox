<?php

declare(strict_types=1);

namespace App\Application\UseCase;

use App\Entity\VoiceWorkerSession;
use App\Repository\TranscriptionRepository;
use App\Repository\VoiceWorkerSessionRepository;

final readonly class GetSessionDetail
{
    public function __construct(
        private VoiceWorkerSessionRepository $sessionRepository,
        private TranscriptionRepository $transcriptionRepository,
    ) {
    }

    /**
     * @return array{session: VoiceWorkerSession, transcriptions: list<\App\Entity\Transcription>}|null
     */
    public function execute(string $sessionId): ?array
    {
        $session = $this->sessionRepository->find($sessionId);
        if (!$session instanceof VoiceWorkerSession) {
            return null;
        }

        return [
            'session' => $session,
            'transcriptions' => $this->transcriptionRepository->findRecentForSession($sessionId),
        ];
    }
}
