<?php

namespace App\Entity;

use App\Repository\CompteurAbsenceRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CompteurAbsenceRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Table(name: 'compteur_absence')]
#[ORM\UniqueConstraint(name: 'unique_user_type_year', columns: ['user_id', 'absence_type_id', 'year'])]
#[UniqueEntity(fields: ['user', 'absenceType', 'year'], message: 'Un compteur existe déjà pour cet utilisateur, ce type d\'absence et cette année.')]
class CompteurAbsence
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: TypeAbsence::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private ?TypeAbsence $absenceType = null;

    #[ORM\Column(type: Types::INTEGER)]
    #[Assert\NotNull]
    #[Assert\GreaterThanOrEqual(2000)]
    #[Assert\LessThanOrEqual(2100)]
    private ?int $year = null;

    #[ORM\Column(type: Types::FLOAT, options: ['default' => 0])]
    #[Assert\GreaterThanOrEqual(0)]
    private float $earned = 0;

    #[ORM\Column(type: Types::FLOAT, options: ['default' => 0])]
    #[Assert\GreaterThanOrEqual(0)]
    private float $taken = 0;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updatedAt = null;

    public function __construct()
    {
        $now = new \DateTime();
        $this->createdAt = $now;
        $this->updatedAt = $now;
        $this->year = (int) $now->format('Y');
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

    public function getAbsenceType(): ?TypeAbsence
    {
        return $this->absenceType;
    }

    public function setAbsenceType(?TypeAbsence $absenceType): static
    {
        $this->absenceType = $absenceType;
        return $this;
    }

    public function getYear(): ?int
    {
        return $this->year;
    }

    public function setYear(int $year): static
    {
        $this->year = $year;
        return $this;
    }

    public function getEarned(): float
    {
        return $this->earned;
    }

    public function setEarned(float $earned): static
    {
        $this->earned = max(0, $earned);
        return $this;
    }

    public function getTaken(): float
    {
        return $this->taken;
    }

    public function setTaken(float $taken): static
    {
        $this->taken = max(0, $taken);
        return $this;
    }

    /**
     * Computed property: remaining days
     */
    public function getRemaining(): float
    {
        return $this->earned - $this->taken;
    }

    /**
     * Check if counter has sufficient balance
     */
    public function hasSufficientBalance(float $days): bool
    {
        return $this->getRemaining() >= $days;
    }

    /**
     * Check if counter is negative
     */
    public function isNegative(): bool
    {
        return $this->getRemaining() < 0;
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
}
