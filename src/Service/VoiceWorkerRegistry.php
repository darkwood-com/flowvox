<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\Enum\WorkerStatus;
use App\Entity\VoiceWorkerSession;
use App\Repository\VoiceWorkerSessionRepository;
use Doctrine\ORM\EntityManagerInterface;

final class VoiceWorkerRegistry
{
    private const HEARTBEAT_INTERVAL_SECONDS = 30;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly VoiceWorkerSessionRepository $repository,
    ) {
    }

    public function register(string $sessionId, int $pid): void
    {
        $existing = $this->repository->find($sessionId);
        if ($existing instanceof VoiceWorkerSession) {
            $this->em->remove($existing);
            $this->em->flush();
        }
        $session = new VoiceWorkerSession($sessionId, $pid);
        $session->setStatus(WorkerStatus::Idle);
        $this->em->persist($session);
        $this->em->flush();
    }

    public function heartbeat(string $sessionId): void
    {
        $session = $this->repository->find($sessionId);
        if ($session instanceof VoiceWorkerSession) {
            $session->touchHeartbeat();
            $this->em->flush();
        }
    }

    public function unregister(string $sessionId): void
    {
        $session = $this->repository->find($sessionId);
        if ($session instanceof VoiceWorkerSession) {
            $this->em->remove($session);
            $this->em->flush();
        }
    }

    /**
     * @return list<array{id: string, pid: int, startedAt: \DateTimeImmutable, lastHeartbeatAt: \DateTimeImmutable}>
     */
    public function listActiveSessions(int $ttlSeconds = self::HEARTBEAT_INTERVAL_SECONDS): array
    {
        $since = new \DateTimeImmutable('-' . $ttlSeconds . ' seconds');
        $sessions = $this->repository->findActiveSessions($since);

        return array_map(
            static fn (VoiceWorkerSession $s): array => [
                'id' => $s->getId(),
                'pid' => $s->getPid(),
                'startedAt' => $s->getStartedAt(),
                'lastHeartbeatAt' => $s->getLastHeartbeatAt(),
            ],
            $sessions,
        );
    }

    public function cleanStale(int $ttlSeconds = self::HEARTBEAT_INTERVAL_SECONDS): int
    {
        $before = new \DateTimeImmutable('-' . $ttlSeconds . ' seconds');

        return $this->repository->removeStaleSessions($before);
    }
}
