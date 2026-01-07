<?php

namespace App\Entity;

use App\Repository\AppointmentParticipantRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AppointmentParticipantRepository::class)]
#[ORM\Table(name: 'appointment_participants')]
#[ORM\UniqueConstraint(name: 'unique_appointment_user', columns: ['appointment_id', 'user_id'])]
#[ORM\HasLifecycleCallbacks]
class AppointmentParticipant
{
    public const PRESENCE_PENDING = 'PENDING';
    public const PRESENCE_CONFIRMED = 'CONFIRMED';
    public const PRESENCE_ABSENT = 'ABSENT';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: RendezVous::class, inversedBy: 'appointmentParticipants')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?RendezVous $appointment = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(length: 20)]
    private string $presenceStatus = self::PRESENCE_PENDING;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $confirmedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAppointment(): ?RendezVous
    {
        return $this->appointment;
    }

    public function setAppointment(?RendezVous $appointment): static
    {
        $this->appointment = $appointment;

        return $this;
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

    public function getPresenceStatus(): string
    {
        return $this->presenceStatus;
    }

    public function setPresenceStatus(string $presenceStatus): static
    {
        $this->presenceStatus = $presenceStatus;

        return $this;
    }

    public function getConfirmedAt(): ?\DateTimeInterface
    {
        return $this->confirmedAt;
    }

    public function setConfirmedAt(?\DateTimeInterface $confirmedAt): static
    {
        $this->confirmedAt = $confirmedAt;

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

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt ??= new \DateTime();
    }

    // Méthodes helper

    public function isPending(): bool
    {
        return $this->presenceStatus === self::PRESENCE_PENDING;
    }

    public function isConfirmed(): bool
    {
        return $this->presenceStatus === self::PRESENCE_CONFIRMED;
    }

    public function isAbsent(): bool
    {
        return $this->presenceStatus === self::PRESENCE_ABSENT;
    }

    public function confirm(): void
    {
        $this->presenceStatus = self::PRESENCE_CONFIRMED;
        $this->confirmedAt = new \DateTime();
    }

    public function markAbsent(): void
    {
        $this->presenceStatus = self::PRESENCE_ABSENT;
        $this->confirmedAt = new \DateTime();
    }
}
