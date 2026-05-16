<?php

declare(strict_types=1);

namespace App\Entity;

use App\Domain\Enum\TranscriptionProviderType;
use App\Repository\TranscriptionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: TranscriptionRepository::class)]
#[ORM\Table(name: 'transcription')]
#[ORM\Index(columns: ['full_text'], name: 'idx_transcription_full_text')]
class Transcription
{
    #[ORM\Id]
    #[ORM\Column(type: 'ulid', unique: true)]
    private Ulid $id;

    #[ORM\ManyToOne(targetEntity: Recording::class, inversedBy: 'transcriptions')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Recording $recording;

    #[ORM\Column(type: Types::STRING, length: 64, enumType: TranscriptionProviderType::class)]
    private TranscriptionProviderType $provider;

    #[ORM\Column(type: Types::STRING, length: 16, nullable: true)]
    private ?string $language = null;

    #[ORM\Column(type: Types::TEXT)]
    private string $fullText = '';

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    /** @var Collection<int, TranscriptionSegment> */
    #[ORM\OneToMany(targetEntity: TranscriptionSegment::class, mappedBy: 'transcription', cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['sequence' => 'ASC'])]
    private Collection $segments;

    public function __construct(Recording $recording, TranscriptionProviderType $provider)
    {
        $this->id = new Ulid();
        $this->recording = $recording;
        $this->provider = $provider;
        $this->createdAt = new \DateTimeImmutable();
        $this->segments = new ArrayCollection();
    }

    public function getId(): Ulid
    {
        return $this->id;
    }

    public function getRecording(): Recording
    {
        return $this->recording;
    }

    public function setRecording(Recording $recording): void
    {
        $this->recording = $recording;
    }

    public function getProvider(): TranscriptionProviderType
    {
        return $this->provider;
    }

    public function getLanguage(): ?string
    {
        return $this->language;
    }

    public function setLanguage(?string $language): void
    {
        $this->language = $language;
    }

    public function getFullText(): string
    {
        return $this->fullText;
    }

    public function setFullText(string $fullText): void
    {
        $this->fullText = $fullText;
    }

    public function appendText(string $text): void
    {
        $this->fullText .= $text;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** @return Collection<int, TranscriptionSegment> */
    public function getSegments(): Collection
    {
        return $this->segments;
    }

    public function addSegment(TranscriptionSegment $segment): void
    {
        if (!$this->segments->contains($segment)) {
            $this->segments->add($segment);
            $segment->setTranscription($this);
        }
    }
}
