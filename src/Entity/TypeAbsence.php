<?php

namespace App\Entity;

use App\Repository\TypeAbsenceRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: TypeAbsenceRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Table(name: 'type_absence')]
class TypeAbsence
{
    // Codes de types d'absence
    public const CODE_REUNION = 'REUNION';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 20)]
    private ?string $code = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    private ?string $label = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    private bool $affectsPlanning = false;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    private bool $deductFromCounter = false;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    private bool $requiresJustification = false;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $justificationDeadlineDays = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $documentType = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    private bool $active = true;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updatedAt = null;

    public function __construct()
    {
        $now = new \DateTime();
        $this->createdAt = $now;
        $this->updatedAt = $now;
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

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = $code;
        return $this;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(string $label): static
    {
        $this->label = $label;
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

    public function isDeductFromCounter(): bool
    {
        return $this->deductFromCounter;
    }

    public function setDeductFromCounter(bool $deductFromCounter): static
    {
        $this->deductFromCounter = $deductFromCounter;
        return $this;
    }

    public function isRequiresJustification(): bool
    {
        return $this->requiresJustification;
    }

    public function setRequiresJustification(bool $requiresJustification): static
    {
        $this->requiresJustification = $requiresJustification;
        return $this;
    }

    public function getJustificationDeadlineDays(): ?int
    {
        return $this->justificationDeadlineDays;
    }

    public function setJustificationDeadlineDays(?int $justificationDeadlineDays): static
    {
        $this->justificationDeadlineDays = $justificationDeadlineDays;
        return $this;
    }

    public function getDocumentType(): ?string
    {
        return $this->documentType;
    }

    public function setDocumentType(?string $documentType): static
    {
        $this->documentType = $documentType;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): static
    {
        $this->active = $active;
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

    public function __toString(): string
    {
        return $this->label ?? '';
    }
}
