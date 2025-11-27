<?php

namespace App\Entity;

use App\Repository\AbsenceRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AbsenceRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Absence
{
    // Statuts d'absence
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_ARCHIVED = 'archived';

    // Statuts justificatif
    public const JUSTIF_NOT_REQUIRED = 'not_required';
    public const JUSTIF_PENDING = 'pending';
    public const JUSTIF_PROVIDED = 'provided';
    public const JUSTIF_VALIDATED = 'validated';
    public const JUSTIF_REJECTED = 'rejected';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    // DEPRECATED: Sera supprimé après migration
    #[ORM\Column(length: 50, nullable: true)]
    private ?string $type = null;

    #[ORM\ManyToOne(targetEntity: TypeAbsence::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?TypeAbsence $absenceType = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $startAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $endAt = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $reason = null;

    #[ORM\Column(length: 30)]
    private string $status = self::STATUS_PENDING;

    #[ORM\OneToMany(mappedBy: 'absence', targetEntity: Document::class)]
    private Collection $documents;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $justificationStatus = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $justificationDeadline = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $validatedBy = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $rejectionReason = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $adminComment = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    private bool $affectsPlanning = false;

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    private ?float $workingDaysCount = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updatedAt = null;

    public function __construct()
    {
        $now = new \DateTime();
        $this->createdAt = $now;
        $this->updatedAt = $now;
        $this->documents = new ArrayCollection();
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $now = new \DateTime();
        $this->createdAt ??= $now;
        $this->updatedAt = $now;
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }

    /**
     * @deprecated Use getAbsenceType() instead. Will be removed after migration.
     */
    public function getType(): ?string
    {
        return $this->type;
    }

    /**
     * @deprecated Use setAbsenceType() instead. Will be removed after migration.
     */
    public function setType(?string $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function getAbsenceType(): ?TypeAbsence
    {
        return $this->absenceType;
    }

    public function setAbsenceType(?TypeAbsence $absenceType): static
    {
        $this->absenceType = $absenceType;
        return $this;
    }

    public function getStartAt(): ?\DateTimeInterface
    {
        return $this->startAt;
    }

    public function setStartAt(?\DateTimeInterface $startAt): static
    {
        $this->startAt = $startAt;
        return $this;
    }

    public function getEndAt(): ?\DateTimeInterface
    {
        return $this->endAt;
    }

    public function setEndAt(?\DateTimeInterface $endAt): static
    {
        $this->endAt = $endAt;
        return $this;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function setReason(?string $reason): static
    {
        $this->reason = $reason;
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

    /**
     * @return Collection<int, Document>
     */
    public function getDocuments(): Collection
    {
        return $this->documents;
    }

    public function addDocument(Document $document): static
    {
        if (!$this->documents->contains($document)) {
            $this->documents->add($document);
            $document->setAbsence($this);
        }

        return $this;
    }

    public function removeDocument(Document $document): static
    {
        if ($this->documents->removeElement($document)) {
            if ($document->getAbsence() === $this) {
                $document->setAbsence(null);
            }
        }

        return $this;
    }

    public function getJustificationStatus(): ?string
    {
        return $this->justificationStatus;
    }

    public function setJustificationStatus(?string $justificationStatus): static
    {
        $this->justificationStatus = $justificationStatus;
        return $this;
    }

    public function getJustificationDeadline(): ?\DateTimeInterface
    {
        return $this->justificationDeadline;
    }

    public function setJustificationDeadline(?\DateTimeInterface $justificationDeadline): static
    {
        $this->justificationDeadline = $justificationDeadline;
        return $this;
    }

    public function getValidatedBy(): ?User
    {
        return $this->validatedBy;
    }

    public function setValidatedBy(?User $validatedBy): static
    {
        $this->validatedBy = $validatedBy;
        return $this;
    }

    public function getRejectionReason(): ?string
    {
        return $this->rejectionReason;
    }

    public function setRejectionReason(?string $rejectionReason): static
    {
        $this->rejectionReason = $rejectionReason;
        return $this;
    }

    public function getAdminComment(): ?string
    {
        return $this->adminComment;
    }

    public function setAdminComment(?string $adminComment): static
    {
        $this->adminComment = $adminComment;
        return $this;
    }

    public function isAffectsPlanning(): bool
    {
        return $this->affectsPlanning;
    }

    public function setAffectsPlanning(bool $affectsPlanning): static
    {
        $this->affectsPlanning = $affectsPlanning;
        return $this;
    }

    public function getWorkingDaysCount(): ?float
    {
        return $this->workingDaysCount;
    }

    public function setWorkingDaysCount(?float $workingDaysCount): static
    {
        $this->workingDaysCount = $workingDaysCount;
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

    public function getDurationInDays(): ?int
    {
        if (!$this->startAt || !$this->endAt) {
            return null;
        }

        return (int) $this->startAt->diff($this->endAt)->format('%a') + 1;
    }

    // ==================== HELPER METHODS ====================

    /**
     * Check if absence has a valid justification
     */
    public function hasValidJustification(): bool
    {
        if ($this->justificationStatus === self::JUSTIF_VALIDATED) {
            return true;
        }

        // Fallback: check if any document is validated (self-healing)
        foreach ($this->documents as $doc) {
            if ($doc->getStatus() === Document::STATUS_VALIDATED) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if absence requires justification
     */
    public function requiresJustification(): bool
    {
        return $this->absenceType?->isRequiresJustification() ?? false;
    }

    /**
     * Check if justification deadline has passed
     */
    public function isJustificationOverdue(): bool
    {
        if (!$this->justificationDeadline) {
            return false;
        }

        return $this->justificationDeadline < new \DateTime()
            && $this->justificationStatus !== self::JUSTIF_VALIDATED;
    }

    /**
     * Check if absence is pending
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if absence is approved
     */
    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    /**
     * Check if absence is rejected
     */
    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    /**
     * Check if absence is cancelled
     */
    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    /**
     * Check if absence can be edited
     */
    public function canBeEdited(): bool
    {
        return $this->isPending();
    }

    /**
     * Check if absence can be cancelled
     */
    public function canBeCancelled(): bool
    {
        return $this->isPending() || $this->isApproved();
    }

    /**
     * Get validated documents count
     */
    public function getValidatedDocumentsCount(): int
    {
        return $this->documents->filter(
            fn(Document $doc) => $doc->getStatus() === Document::STATUS_VALIDATED
        )->count();
    }

    /**
     * Get pending documents count
     */
    public function getPendingDocumentsCount(): int
    {
        return $this->documents->filter(
            fn(Document $doc) => $doc->getStatus() === Document::STATUS_PENDING
        )->count();
    }
}
