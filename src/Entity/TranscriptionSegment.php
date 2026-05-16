<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'transcription_segment')]
#[ORM\Index(columns: ['transcription_id', 'sequence'], name: 'idx_segment_transcription_sequence')]
class TranscriptionSegment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Transcription::class, inversedBy: 'segments')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Transcription $transcription;

    #[ORM\Column(type: Types::INTEGER)]
    private int $sequence;

    #[ORM\Column(type: Types::TEXT)]
    private string $text;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $isFinal;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct(Transcription $transcription, int $sequence, string $text, bool $isFinal)
    {
        $this->transcription = $transcription;
        $this->sequence = $sequence;
        $this->text = $text;
        $this->isFinal = $isFinal;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTranscription(): Transcription
    {
        return $this->transcription;
    }

    public function setTranscription(Transcription $transcription): void
    {
        $this->transcription = $transcription;
    }

    public function getSequence(): int
    {
        return $this->sequence;
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function isFinal(): bool
    {
        return $this->isFinal;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
