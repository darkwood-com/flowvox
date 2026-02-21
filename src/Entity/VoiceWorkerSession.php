<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: \App\Repository\VoiceWorkerSessionRepository::class)]
#[ORM\Table(name: 'voice_worker_session')]
class VoiceWorkerSession
{
    #[ORM\Id]
    #[ORM\Column(type: Types::STRING, length: 64)]
    private string $id;

    #[ORM\Column(type: Types::INTEGER)]
    private int $pid;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $startedAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $lastHeartbeatAt;

    public function __construct(string $id, int $pid)
    {
        $this->id = $id;
        $this->pid = $pid;
        $this->startedAt = new \DateTimeImmutable();
        $this->lastHeartbeatAt = new \DateTimeImmutable();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getPid(): int
    {
        return $this->pid;
    }

    public function getStartedAt(): \DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function getLastHeartbeatAt(): \DateTimeImmutable
    {
        return $this->lastHeartbeatAt;
    }

    public function touchHeartbeat(): void
    {
        $this->lastHeartbeatAt = new \DateTimeImmutable();
    }
}
