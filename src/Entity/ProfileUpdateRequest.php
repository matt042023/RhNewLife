<?php

namespace App\Entity;

use App\Repository\ProfileUpdateRequestRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProfileUpdateRequestRepository::class)]
#[ORM\HasLifecycleCallbacks]
class ProfileUpdateRequest
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    public const STATUSES = [
        self::STATUS_PENDING => 'En attente',
        self::STATUS_APPROVED => 'Approuvée',
        self::STATUS_REJECTED => 'Rejetée',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(type: Types::JSON)]
    private array $requestedData = [];

    #[ORM\Column(length: 20, options: ['default' => self::STATUS_PENDING])]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $reason = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $requestedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $processedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $processedBy = null;

    public function __construct()
    {
        $this->requestedAt = new \DateTime();
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

    public function getRequestedData(): array
    {
        return $this->requestedData;
    }

    public function setRequestedData(array $requestedData): static
    {
        $this->requestedData = $requestedData;
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

    public function getStatusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
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

    public function getRequestedAt(): ?\DateTimeInterface
    {
        return $this->requestedAt;
    }

    public function setRequestedAt(\DateTimeInterface $requestedAt): static
    {
        $this->requestedAt = $requestedAt;
        return $this;
    }

    public function getProcessedAt(): ?\DateTimeInterface
    {
        return $this->processedAt;
    }

    public function setProcessedAt(?\DateTimeInterface $processedAt): static
    {
        $this->processedAt = $processedAt;
        return $this;
    }

    public function getProcessedBy(): ?User
    {
        return $this->processedBy;
    }

    public function setProcessedBy(?User $processedBy): static
    {
        $this->processedBy = $processedBy;
        return $this;
    }

    // Helper methods

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    public function markAsApproved(User $processedBy): static
    {
        $this->status = self::STATUS_APPROVED;
        $this->processedAt = new \DateTime();
        $this->processedBy = $processedBy;
        return $this;
    }

    public function markAsRejected(string $reason, User $processedBy): static
    {
        $this->status = self::STATUS_REJECTED;
        $this->reason = $reason;
        $this->processedAt = new \DateTime();
        $this->processedBy = $processedBy;
        return $this;
    }

    /**
     * Retourne un résumé des modifications demandées
     */
    public function getSummary(): string
    {
        $fields = [];
        foreach (array_keys($this->requestedData) as $field) {
            $fields[] = $this->getFieldLabel($field);
        }

        return implode(', ', $fields);
    }

    /**
     * Traduit le nom du champ en français
     */
    private function getFieldLabel(string $field): string
    {
        return match($field) {
            'phone' => 'Téléphone',
            'address' => 'Adresse',
            'iban' => 'IBAN',
            'bic' => 'BIC',
            'familyStatus' => 'Situation familiale',
            'children' => 'Nombre d\'enfants',
            default => $field,
        };
    }
}
