<?php

declare(strict_types=1);

namespace App\Application\UseCase;

use App\Repository\VoiceWorkerSessionRepository;

final readonly class ListActiveSessions
{
    public function __construct(
        private VoiceWorkerSessionRepository $repository,
    ) {
    }

    /**
     * @return list<array{id: string, pid: int, startedAt: \DateTimeImmutable, lastHeartbeatAt: \DateTimeImmutable, status: string, label: ?string, provider: string}>
     */
    public function execute(int $ttlSeconds = 30): array
    {
        $since = new \DateTimeImmutable('-' . $ttlSeconds . ' seconds');
        $sessions = $this->repository->findActiveSessions($since);

        return array_map(
            static fn ($s): array => [
                'id' => $s->getId(),
                'pid' => $s->getPid(),
                'startedAt' => $s->getStartedAt(),
                'lastHeartbeatAt' => $s->getLastHeartbeatAt(),
                'status' => $s->getStatus()->value,
                'label' => $s->getLabel(),
                'provider' => $s->getProvider()->value,
            ],
            $sessions,
        );
    }
}
