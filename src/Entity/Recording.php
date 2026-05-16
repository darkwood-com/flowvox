<?php

declare(strict_types=1);

namespace App\Entity;

use App\Domain\Enum\RecordingStatus;
use App\Repository\RecordingRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: RecordingRepository::class)]
#[ORM\Table(name: 'recording')]
class Recording
{
    #[ORM\Id]
    #[ORM\Column(type: 'ulid', unique: true)]
    private Ulid $id;

    #[ORM\ManyToOne(targetEntity: VoiceWorkerSession::class, inversedBy: 'recordings')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private VoiceWorkerSession $session;

    #[ORM\Column(type: Types::STRING, length: 1024)]
    private string $wavPath;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $startedAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $endedAt = null;

    #[ORM\Column(type: Types::STRING, length: 32, enumType: RecordingStatus::class)]
    private RecordingStatus $status = RecordingStatus::Recording;

    /** @var Collection<int, Transcription> */
    #[ORM\OneToMany(targetEntity: Transcription::class, mappedBy: 'recording', cascade: ['persist'], orphanRemoval: true)]
    private Collection $transcriptions;

    public function __construct(VoiceWorkerSession $session, string $wavPath)
    {
        $this->id = new Ulid();
        $this->session = $session;
        $this->wavPath = $wavPath;
        $this->startedAt = new \DateTimeImmutable();
        $this->transcriptions = new ArrayCollection();
    }

    public function getId(): Ulid
    {
        return $this->id;
    }

    public function getSession(): VoiceWorkerSession
    {
        return $this->session;
    }

    public function setSession(VoiceWorkerSession $session): void
    {
        $this->session = $session;
    }

    public function getWavPath(): string
    {
        return $this->wavPath;
    }

    public function getStartedAt(): \DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function getEndedAt(): ?\DateTimeImmutable
    {
        return $this->endedAt;
    }

    public function setEndedAt(?\DateTimeImmutable $endedAt): void
    {
        $this->endedAt = $endedAt;
    }

    public function getStatus(): RecordingStatus
    {
        return $this->status;
    }

    public function setStatus(RecordingStatus $status): void
    {
        $this->status = $status;
    }

    /** @return Collection<int, Transcription> */
    public function getTranscriptions(): Collection
    {
        return $this->transcriptions;
    }

    public function addTranscription(Transcription $transcription): void
    {
        if (!$this->transcriptions->contains($transcription)) {
            $this->transcriptions->add($transcription);
            $transcription->setRecording($this);
        }
    }
}
