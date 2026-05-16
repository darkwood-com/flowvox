<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Enum\RecordingStatus;
use App\Domain\Enum\VoiceDomainEventType;
use App\Domain\Enum\WorkerStatus;
use App\Domain\Event\VoiceDomainEvent;
use App\Entity\Recording;
use App\Entity\Transcription;
use App\Entity\TranscriptionSegment;
use App\Entity\VoiceWorkerSession;
use App\Repository\VoiceWorkerSessionRepository;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineVoiceEventStore
{
    /** @var array<string, Recording> */
    private array $activeRecordings = [];

    /** @var array<string, Transcription> */
    private array $activeTranscriptions = [];

    /** @var array<string, int> */
    private array $segmentSequences = [];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly VoiceWorkerSessionRepository $sessionRepository,
    ) {
    }

    public function record(VoiceDomainEvent $event): void
    {
        $session = $this->sessionRepository->find($event->sessionId);
        if (!$session instanceof VoiceWorkerSession) {
            return;
        }

        match ($event->type) {
            VoiceDomainEventType::Heartbeat => $session->touchHeartbeat(),
            VoiceDomainEventType::RecordingStarted => $this->onRecordingStarted($session, $event),
            VoiceDomainEventType::RecordingStopped => $this->onRecordingStopped($session, $event),
            VoiceDomainEventType::TranscriptionPartial => $this->onTranscriptionPartial($session, $event),
            VoiceDomainEventType::TranscriptionFinal => $this->onTranscriptionFinal($session, $event),
            VoiceDomainEventType::Error => $this->onError($session, $event),
        };

        $this->em->flush();
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function onRecordingStarted(VoiceWorkerSession $session, VoiceDomainEvent $event): void
    {
        $wavPath = (string) ($event->payload['wavPath'] ?? '');
        $recording = new Recording($session, $wavPath);
        $session->addRecording($recording);
        $session->setStatus(WorkerStatus::Recording);
        $this->em->persist($recording);
        $this->activeRecordings[$session->getId()] = $recording;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function onRecordingStopped(VoiceWorkerSession $session, VoiceDomainEvent $event): void
    {
        $recording = $this->activeRecordings[$session->getId()] ?? null;
        if ($recording instanceof Recording) {
            $recording->setStatus(RecordingStatus::Finalizing);
            $wavPath = (string) ($event->payload['wavPath'] ?? $recording->getWavPath());
            if ($wavPath !== '') {
                $recording->setEndedAt(new \DateTimeImmutable());
            }
        }
        $session->setStatus(WorkerStatus::Stopping);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function onTranscriptionPartial(VoiceWorkerSession $session, VoiceDomainEvent $event): void
    {
        $session->setStatus(WorkerStatus::Transcribing);
        $transcription = $this->getOrCreateTranscription($session);
        $text = (string) ($event->payload['text'] ?? '');
        $sequence = $this->nextSequence($session->getId());
        $segment = new TranscriptionSegment($transcription, $sequence, $text, false);
        $transcription->addSegment($segment);
        $transcription->appendText($text);
        $this->em->persist($segment);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function onTranscriptionFinal(VoiceWorkerSession $session, VoiceDomainEvent $event): void
    {
        $text = (string) ($event->payload['text'] ?? '');
        $transcription = $this->getOrCreateTranscription($session);
        $transcription->setFullText($text);
        if (isset($event->payload['language'])) {
            $transcription->setLanguage((string) $event->payload['language']);
        }
        $sequence = $this->nextSequence($session->getId());
        $segment = new TranscriptionSegment($transcription, $sequence, $text, true);
        $transcription->addSegment($segment);
        $this->em->persist($segment);

        $recording = $this->activeRecordings[$session->getId()] ?? null;
        if ($recording instanceof Recording) {
            $recording->setStatus(RecordingStatus::Completed);
            $recording->setEndedAt($recording->getEndedAt() ?? new \DateTimeImmutable());
        }

        $session->setStatus(WorkerStatus::Idle);
        unset($this->activeRecordings[$session->getId()], $this->activeTranscriptions[$session->getId()], $this->segmentSequences[$session->getId()]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function onError(VoiceWorkerSession $session, VoiceDomainEvent $event): void
    {
        $session->setStatus(WorkerStatus::Error);
        $session->setLastError((string) ($event->payload['message'] ?? 'Unknown error'));
    }

    private function getOrCreateTranscription(VoiceWorkerSession $session): Transcription
    {
        if (isset($this->activeTranscriptions[$session->getId()])) {
            return $this->activeTranscriptions[$session->getId()];
        }

        $recording = $this->activeRecordings[$session->getId()] ?? null;
        if (!$recording instanceof Recording) {
            $recording = new Recording($session, '');
            $session->addRecording($recording);
            $this->em->persist($recording);
            $this->activeRecordings[$session->getId()] = $recording;
        }

        $transcription = new Transcription($recording, $session->getProvider());
        $recording->addTranscription($transcription);
        $this->em->persist($transcription);
        $this->activeTranscriptions[$session->getId()] = $transcription;

        return $transcription;
    }

    private function nextSequence(string $sessionId): int
    {
        $this->segmentSequences[$sessionId] = ($this->segmentSequences[$sessionId] ?? 0) + 1;

        return $this->segmentSequences[$sessionId];
    }
}
