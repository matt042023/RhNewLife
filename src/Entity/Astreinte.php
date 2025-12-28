<?php

namespace App\Entity;

use App\Repository\AstreinteRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AstreinteRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Astreinte
{
    // Status constants
    public const STATUS_ASSIGNED = 'assigned';        // Éducateur affecté
    public const STATUS_UNASSIGNED = 'unassigned';    // Aucun éducateur
    public const STATUS_ALERT = 'alert';              // Éducateur absent ou problème

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // Relation: Which educator is on-call
    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'astreintes')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $educateur = null;

    // Custom period (not restricted to ISO weeks)
    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $startAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $endAt = null;

    // Optional: Label for the period (e.g., "S48", "Semaine 48")
    #[ORM\Column(length: 50, nullable: true)]
    private ?string $periodLabel = null;

    // Status
    #[ORM\Column(length: 30)]
    private string $status = self::STATUS_UNASSIGNED;

    // Tracking replacements that occurred (for stats)
    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $replacementCount = 0;

    // Optional: Notes/comments
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    // Audit fields
    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $createdBy = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $updatedBy = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
        $this->status = self::STATUS_UNASSIGNED;
        $this->replacementCount = 0;
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTime();
    }

    // Helper: Check if period is in the past
    public function isPast(): bool
    {
        return $this->endAt < new \DateTime();
    }

    // Helper: Check if period is current
    public function isCurrent(): bool
    {
        $now = new \DateTime();
        return $this->startAt <= $now && $this->endAt >= $now;
    }

    // Helper: Check if assigned
    public function isAssigned(): bool
    {
        return $this->educateur !== null;
    }

    // Helper: Get duration in days
    public function getDurationInDays(): int
    {
        return (int) $this->startAt->diff($this->endAt)->format('%a') + 1;
    }

    // Helper: Increment replacement count
    public function incrementReplacementCount(): void
    {
        $this->replacementCount++;
    }

    // Getters and Setters

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEducateur(): ?User
    {
        return $this->educateur;
    }

    public function setEducateur(?User $educateur): static
    {
        $this->educateur = $educateur;

        return $this;
    }

    public function getStartAt(): ?\DateTimeInterface
    {
        return $this->startAt;
    }

    public function setStartAt(\DateTimeInterface $startAt): static
    {
        $this->startAt = $startAt;

        return $this;
    }

    public function getEndAt(): ?\DateTimeInterface
    {
        return $this->endAt;
    }

    public function setEndAt(\DateTimeInterface $endAt): static
    {
        $this->endAt = $endAt;

        return $this;
    }

    public function getPeriodLabel(): ?string
    {
        return $this->periodLabel;
    }

    public function setPeriodLabel(?string $periodLabel): static
    {
        $this->periodLabel = $periodLabel;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getReplacementCount(): int
    {
        return $this->replacementCount;
    }

    public function setReplacementCount(int $replacementCount): static
    {
        $this->replacementCount = $replacementCount;

        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $createdBy): static
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    public function getUpdatedBy(): ?User
    {
        return $this->updatedBy;
    }

    public function setUpdatedBy(?User $updatedBy): static
    {
        $this->updatedBy = $updatedBy;

        return $this;
    }
}
