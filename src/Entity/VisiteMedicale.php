<?php

namespace App\Entity;

use App\Repository\VisiteMedicaleRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: VisiteMedicaleRepository::class)]
#[ORM\HasLifecycleCallbacks]
class VisiteMedicale
{
    // Types de visites médicales
    public const TYPE_EMBAUCHE = 'embauche';
    public const TYPE_PERIODIQUE = 'periodique';
    public const TYPE_REPRISE = 'reprise';
    public const TYPE_DEMANDE = 'demande';

    // Statuts de la visite
    public const STATUS_PROGRAMMEE = 'programmee';
    public const STATUS_EFFECTUEE = 'effectuee';
    public const STATUS_ANNULEE = 'annulee';

    // Niveaux d'aptitude
    public const APTITUDE_APTE = 'apte';
    public const APTITUDE_APTE_AVEC_RESERVE = 'apte_avec_reserve';
    public const APTITUDE_INAPTE = 'inapte';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'visitesMedicales')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\OneToOne(inversedBy: 'visiteMedicale', targetEntity: RendezVous::class)]
    #[ORM\JoinColumn(name: 'appointment_id', nullable: true, onDelete: 'SET NULL')]
    private ?RendezVous $appointment = null;

    #[ORM\Column(length: 50)]
    private ?string $type = null;

    #[ORM\Column(length: 50)]
    private string $status = self::STATUS_PROGRAMMEE;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $visitDate = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $expiryDate = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $medicalOrganization = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $aptitude = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $observations = null;

    /**
     * @var Collection<int, Document>
     */
    #[ORM\OneToMany(mappedBy: 'visiteMedicale', targetEntity: Document::class)]
    private Collection $documents;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updatedAt = null;

    public function __construct()
    {
        $this->documents = new ArrayCollection();
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

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
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

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

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

    public function getVisitDate(): ?\DateTimeInterface
    {
        return $this->visitDate;
    }

    public function setVisitDate(?\DateTimeInterface $visitDate): static
    {
        $this->visitDate = $visitDate;

        return $this;
    }

    public function getExpiryDate(): ?\DateTimeInterface
    {
        return $this->expiryDate;
    }

    public function setExpiryDate(?\DateTimeInterface $expiryDate): static
    {
        $this->expiryDate = $expiryDate;

        return $this;
    }

    public function getMedicalOrganization(): ?string
    {
        return $this->medicalOrganization;
    }

    public function setMedicalOrganization(?string $medicalOrganization): static
    {
        $this->medicalOrganization = $medicalOrganization;

        return $this;
    }

    public function getAptitude(): ?string
    {
        return $this->aptitude;
    }

    public function setAptitude(?string $aptitude): static
    {
        $this->aptitude = $aptitude;

        return $this;
    }

    public function getObservations(): ?string
    {
        return $this->observations;
    }

    public function setObservations(?string $observations): static
    {
        $this->observations = $observations;

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
            $document->setVisiteMedicale($this);
        }

        return $this;
    }

    public function removeDocument(Document $document): static
    {
        if ($this->documents->removeElement($document)) {
            // set the owning side to null (unless already changed)
            if ($document->getVisiteMedicale() === $this) {
                $document->setVisiteMedicale(null);
            }
        }

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

    // Helper methods

    public function isProgrammee(): bool
    {
        return $this->status === self::STATUS_PROGRAMMEE;
    }

    public function isEffectuee(): bool
    {
        return $this->status === self::STATUS_EFFECTUEE;
    }

    public function isAnnulee(): bool
    {
        return $this->status === self::STATUS_ANNULEE;
    }

    public function isCompleted(): bool
    {
        return $this->aptitude !== null;
    }

    public function isExpired(): bool
    {
        if (!$this->expiryDate || !$this->isEffectuee()) {
            return false;
        }

        return $this->expiryDate < new \DateTime();
    }

    public function isExpiringSoon(int $days = 30): bool
    {
        if (!$this->expiryDate || !$this->isEffectuee() || $this->isExpired()) {
            return false;
        }

        $threshold = new \DateTime("+{$days} days");
        return $this->expiryDate <= $threshold;
    }

    public function getDaysUntilExpiry(): ?int
    {
        if (!$this->expiryDate || !$this->isEffectuee()) {
            return null;
        }

        $now = new \DateTime();
        $interval = $now->diff($this->expiryDate);

        return $interval->invert ? -$interval->days : $interval->days;
    }

    public function isFitForWork(): bool
    {
        return $this->aptitude === self::APTITUDE_APTE || $this->aptitude === self::APTITUDE_APTE_AVEC_RESERVE;
    }

    public function hasRestrictions(): bool
    {
        return $this->aptitude === self::APTITUDE_APTE_AVEC_RESERVE || $this->aptitude === self::APTITUDE_INAPTE;
    }

    public function getTypeLabel(): string
    {
        return match($this->type) {
            self::TYPE_EMBAUCHE => 'Visite d\'embauche',
            self::TYPE_PERIODIQUE => 'Visite périodique',
            self::TYPE_REPRISE => 'Visite de reprise',
            self::TYPE_DEMANDE => 'Visite à la demande',
            default => $this->type
        };
    }

    public function getStatusLabel(): string
    {
        return match($this->status) {
            self::STATUS_PROGRAMMEE => 'Programmée',
            self::STATUS_EFFECTUEE => 'Effectuée',
            self::STATUS_ANNULEE => 'Annulée',
            default => $this->status
        };
    }

    public function getAptitudeLabel(): string
    {
        return match($this->aptitude) {
            self::APTITUDE_APTE => 'Apte',
            self::APTITUDE_APTE_AVEC_RESERVE => 'Apte avec réserve',
            self::APTITUDE_INAPTE => 'Inapte',
            default => ''
        };
    }
}
