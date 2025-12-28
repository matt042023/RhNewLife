<?php

namespace App\Entity;

use App\Repository\RendezVousRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RendezVousRepository::class)]
#[ORM\HasLifecycleCallbacks]
class RendezVous
{
    // Anciens types (deprecated, pour compatibilité)
    public const TYPE_INDIVIDUEL = 'individuel';
    public const TYPE_GROUPE = 'groupe';

    // Nouveaux types
    public const TYPE_CONVOCATION = 'CONVOCATION';
    public const TYPE_DEMANDE = 'DEMANDE';
    public const TYPE_VISITE_MEDICALE = 'VISITE_MEDICALE';

    // Anciens statuts (deprecated, pour compatibilité)
    public const STATUS_PLANNED = 'planned';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    // Nouveaux statuts
    public const STATUS_EN_ATTENTE = 'EN_ATTENTE';
    public const STATUS_CONFIRME = 'CONFIRME';
    public const STATUS_REFUSE = 'REFUSE';
    public const STATUS_ANNULE = 'ANNULE';
    public const STATUS_TERMINE = 'TERMINE';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $titre = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $startAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $endAt = null;

    #[ORM\Column(length: 20)]
    private ?string $type = null;

    #[ORM\Column]
    private ?bool $impactGarde = false;

    #[ORM\Column(length: 20)]
    private ?string $statut = self::STATUS_PLANNED;

    #[ORM\Column(length: 7, nullable: true)]
    private ?string $couleur = null;

    #[ORM\ManyToMany(targetEntity: User::class)]
    private Collection $participants;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $createdBy = null;

    // Nouveaux champs
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $organizer = null;

    #[ORM\Column(nullable: true)]
    private ?int $durationMinutes = 60;

    #[ORM\Column(length: 255)]
    private ?string $subject = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $location = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $createsAbsence = false;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $refusalReason = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updatedAt = null;

    /**
     * @var Collection<int, AppointmentParticipant>
     */
    #[ORM\OneToMany(mappedBy: 'appointment', targetEntity: AppointmentParticipant::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $appointmentParticipants;

    #[ORM\OneToOne(mappedBy: 'appointment', targetEntity: VisiteMedicale::class, cascade: ['persist'])]
    private ?VisiteMedicale $visiteMedicale = null;

    public function __construct()
    {
        $this->participants = new ArrayCollection();
        $this->appointmentParticipants = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitre(): ?string
    {
        return $this->titre;
    }

    public function setTitre(string $titre): static
    {
        $this->titre = $titre;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

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

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function isImpactGarde(): ?bool
    {
        return $this->impactGarde;
    }

    public function setImpactGarde(bool $impactGarde): static
    {
        $this->impactGarde = $impactGarde;

        return $this;
    }

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): static
    {
        $this->statut = $statut;

        return $this;
    }

    public function getCouleur(): ?string
    {
        return $this->couleur;
    }

    public function setCouleur(?string $couleur): static
    {
        $this->couleur = $couleur;

        return $this;
    }

    /**
     * @return Collection<int, User>
     */
    public function getParticipants(): Collection
    {
        return $this->participants;
    }

    public function addParticipant(User $participant): static
    {
        if (!$this->participants->contains($participant)) {
            $this->participants->add($participant);
        }

        return $this;
    }

    public function removeParticipant(User $participant): static
    {
        $this->participants->removeElement($participant);

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

    // Nouveaux getters/setters

    public function getOrganizer(): ?User
    {
        return $this->organizer;
    }

    public function setOrganizer(?User $organizer): static
    {
        $this->organizer = $organizer;

        return $this;
    }

    public function getDurationMinutes(): ?int
    {
        return $this->durationMinutes;
    }

    public function setDurationMinutes(?int $durationMinutes): static
    {
        $this->durationMinutes = $durationMinutes;

        return $this;
    }

    public function getSubject(): ?string
    {
        return $this->subject;
    }

    public function setSubject(?string $subject): static
    {
        $this->subject = $subject;

        return $this;
    }

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function setLocation(?string $location): static
    {
        $this->location = $location;

        return $this;
    }

    public function isCreatesAbsence(): bool
    {
        return $this->createsAbsence;
    }

    public function setCreatesAbsence(bool $createsAbsence): static
    {
        $this->createsAbsence = $createsAbsence;

        return $this;
    }

    public function getRefusalReason(): ?string
    {
        return $this->refusalReason;
    }

    public function setRefusalReason(?string $refusalReason): static
    {
        $this->refusalReason = $refusalReason;

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

    /**
     * @return Collection<int, AppointmentParticipant>
     */
    public function getAppointmentParticipants(): Collection
    {
        return $this->appointmentParticipants;
    }

    public function addAppointmentParticipant(AppointmentParticipant $appointmentParticipant): static
    {
        if (!$this->appointmentParticipants->contains($appointmentParticipant)) {
            $this->appointmentParticipants->add($appointmentParticipant);
            $appointmentParticipant->setAppointment($this);
        }

        return $this;
    }

    public function removeAppointmentParticipant(AppointmentParticipant $appointmentParticipant): static
    {
        if ($this->appointmentParticipants->removeElement($appointmentParticipant)) {
            if ($appointmentParticipant->getAppointment() === $this) {
                $appointmentParticipant->setAppointment(null);
            }
        }

        return $this;
    }

    // Lifecycle callbacks

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

    // Méthodes helper

    public function isConvocation(): bool
    {
        return $this->type === self::TYPE_CONVOCATION;
    }

    public function isDemande(): bool
    {
        return $this->type === self::TYPE_DEMANDE;
    }

    public function isVisiteMedicale(): bool
    {
        return $this->type === self::TYPE_VISITE_MEDICALE;
    }

    public function isEnAttente(): bool
    {
        return $this->statut === self::STATUS_EN_ATTENTE;
    }

    public function isConfirme(): bool
    {
        return $this->statut === self::STATUS_CONFIRME;
    }

    public function isRefuse(): bool
    {
        return $this->statut === self::STATUS_REFUSE;
    }

    public function isAnnule(): bool
    {
        return $this->statut === self::STATUS_ANNULE;
    }

    public function isTermine(): bool
    {
        return $this->statut === self::STATUS_TERMINE;
    }

    public function canBeValidated(): bool
    {
        return $this->isDemande() && $this->isEnAttente();
    }

    public function canBeEdited(): bool
    {
        return !$this->isTermine() && !$this->isRefuse();
    }

    public function hasParticipant(User $user): bool
    {
        foreach ($this->appointmentParticipants as $participant) {
            if ($participant->getUser() === $user) {
                return true;
            }
        }
        return false;
    }

    public function getParticipantStatus(User $user): ?string
    {
        foreach ($this->appointmentParticipants as $participant) {
            if ($participant->getUser() === $user) {
                return $participant->getPresenceStatus();
            }
        }
        return null;
    }

    public function addParticipantWithStatus(User $user, string $status = 'PENDING'): static
    {
        if (!$this->hasParticipant($user)) {
            $appointmentParticipant = new AppointmentParticipant();
            $appointmentParticipant->setUser($user);
            $appointmentParticipant->setPresenceStatus($status);
            $this->addAppointmentParticipant($appointmentParticipant);
        }

        return $this;
    }

    public function getAllConfirmed(): bool
    {
        if ($this->appointmentParticipants->isEmpty()) {
            return false;
        }

        foreach ($this->appointmentParticipants as $participant) {
            if ($participant->getPresenceStatus() !== AppointmentParticipant::PRESENCE_CONFIRMED) {
                return false;
            }
        }

        return true;
    }

    /**
     * Calcule la date de fin basée sur startAt et durationMinutes
     */
    public function getCalculatedEndAt(): ?\DateTimeInterface
    {
        if ($this->startAt && $this->durationMinutes) {
            $endAt = clone $this->startAt;
            $endAt->modify("+{$this->durationMinutes} minutes");
            return $endAt;
        }
        return $this->endAt;
    }

    /**
     * Vérifie si le rendez-vous est passé (a déjà eu lieu)
     */
    public function isPast(): bool
    {
        if (!$this->getCalculatedEndAt()) {
            return false;
        }
        return $this->getCalculatedEndAt() < new \DateTime();
    }

    /**
     * Vérifie si le rendez-vous est à venir
     */
    public function isUpcoming(): bool
    {
        if (!$this->startAt) {
            return false;
        }
        return $this->startAt > new \DateTime();
    }

    /**
     * Vérifie si le rendez-vous est en cours
     */
    public function isOngoing(): bool
    {
        if (!$this->startAt || !$this->getCalculatedEndAt()) {
            return false;
        }
        $now = new \DateTime();
        return $this->startAt <= $now && $this->getCalculatedEndAt() >= $now;
    }

    /**
     * Retourne le statut d'affichage réel basé sur la date et les confirmations
     */
    public function getDisplayStatus(): string
    {
        // Si annulé ou refusé, retourner tel quel
        if ($this->isAnnule() || $this->isRefuse()) {
            return $this->statut;
        }

        // Si en attente de validation, retourner tel quel
        if ($this->isEnAttente()) {
            return $this->statut;
        }

        // Si le RDV est passé
        if ($this->isPast()) {
            return self::STATUS_TERMINE;
        }

        // Si confirmé et tous les participants ont confirmé
        if ($this->isConfirme() && $this->getAllConfirmed()) {
            return 'PRET'; // Tous prêts, en attente du RDV
        }

        // Sinon retourner le statut actuel
        return $this->statut;
    }

    /**
     * Retourne le label du statut d'affichage
     */
    public function getDisplayStatusLabel(): string
    {
        $status = $this->getDisplayStatus();

        return match($status) {
            'EN_ATTENTE' => 'En attente',
            'CONFIRME' => 'Confirmé',
            'PRET' => 'Prêt',
            'REFUSE' => 'Refusé',
            'ANNULE' => 'Annulé',
            'TERMINE' => 'Terminé',
            default => $status
        };
    }

    public function getTypeLabel(): string
    {
        return match($this->type) {
            self::TYPE_CONVOCATION => 'Convocation',
            self::TYPE_DEMANDE => 'Demande',
            self::TYPE_VISITE_MEDICALE => 'Visite médicale',
            self::TYPE_INDIVIDUEL => 'Individuel',
            self::TYPE_GROUPE => 'Groupe',
            default => $this->type
        };
    }

    public function getVisiteMedicale(): ?VisiteMedicale
    {
        return $this->visiteMedicale;
    }

    public function setVisiteMedicale(?VisiteMedicale $visiteMedicale): static
    {
        // Unset the owning side of the relation if necessary
        if ($visiteMedicale === null && $this->visiteMedicale !== null) {
            $this->visiteMedicale->setAppointment(null);
        }

        // Set the owning side of the relation if necessary
        if ($visiteMedicale !== null && $visiteMedicale->getAppointment() !== $this) {
            $visiteMedicale->setAppointment($this);
        }

        $this->visiteMedicale = $visiteMedicale;

        return $this;
    }

    public function hasCompletedMedicalVisit(): bool
    {
        return $this->visiteMedicale !== null && $this->visiteMedicale->isEffectuee();
    }
}
