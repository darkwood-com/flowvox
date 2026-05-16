<?php

declare(strict_types=1);

namespace App\Entity;

use App\Domain\Enum\TranscriptionProviderType;
use App\Domain\Enum\WorkerStatus;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
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

    #[ORM\Column(type: Types::STRING, length: 32, enumType: WorkerStatus::class)]
    private WorkerStatus $status = WorkerStatus::Idle;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $label = null;

    #[ORM\Column(type: Types::STRING, length: 64, enumType: TranscriptionProviderType::class)]
    private TranscriptionProviderType $provider = TranscriptionProviderType::WhisperCpp;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $lastError = null;

    /** @var Collection<int, Recording> */
    #[ORM\OneToMany(targetEntity: Recording::class, mappedBy: 'session', cascade: ['persist'], orphanRemoval: true)]
    private Collection $recordings;

    public function __construct(string $id, int $pid)
    {
        $this->id = $id;
        $this->pid = $pid;
        $this->startedAt = new \DateTimeImmutable();
        $this->lastHeartbeatAt = new \DateTimeImmutable();
        $this->recordings = new ArrayCollection();
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

    public function getStatus(): WorkerStatus
    {
        return $this->status;
    }

    public function setStatus(WorkerStatus $status): void
    {
        $this->status = $status;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(?string $label): void
    {
        $this->label = $label;
    }

    public function getProvider(): TranscriptionProviderType
    {
        return $this->provider;
    }

    public function setProvider(TranscriptionProviderType $provider): void
    {
        $this->provider = $provider;
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    public function setLastError(?string $lastError): void
    {
        $this->lastError = $lastError;
    }

    /** @return Collection<int, Recording> */
    public function getRecordings(): Collection
    {
        return $this->recordings;
    }

    public function addRecording(Recording $recording): void
    {
        if (!$this->recordings->contains($recording)) {
            $this->recordings->add($recording);
            $recording->setSession($this);
        }
    }
}
