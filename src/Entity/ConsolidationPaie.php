<?php

namespace App\Entity;

use App\Repository\ConsolidationPaieRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ConsolidationPaieRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\UniqueConstraint(name: 'unique_user_period', columns: ['user_id', 'period'])]
class ConsolidationPaie
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_VALIDATED = 'validated';
    public const STATUS_EXPORTED = 'exported';
    public const STATUS_ARCHIVED = 'archived';

    public const STATUSES = [
        self::STATUS_DRAFT => 'Brouillon',
        self::STATUS_VALIDATED => 'Validé',
        self::STATUS_EXPORTED => 'Exporté',
        self::STATUS_ARCHIVED => 'Archivé',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    /**
     * Format: YYYY-MM (ex: 2026-01)
     */
    #[ORM\Column(length: 7)]
    private ?string $period = null;

    #[ORM\Column(length: 20, options: ['default' => self::STATUS_DRAFT])]
    private string $status = self::STATUS_DRAFT;

    /**
     * Jours travaillés depuis les affectations (gardes)
     */
    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, options: ['default' => 0])]
    private string $joursTravailes = '0.00';

    /**
     * Jours travaillés depuis les événements (RDV, réunions, formations) hors jours de garde
     */
    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, options: ['default' => 0])]
    private string $joursEvenements = '0.00';

    /**
     * Absences par type (JSON: {"CP": 2, "MAL": 1, ...})
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $joursAbsence = null;

    /**
     * CP acquis ce mois (2.5j × prorata)
     */
    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, options: ['default' => 0])]
    private string $cpAcquis = '0.00';

    /**
     * CP pris ce mois
     */
    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, options: ['default' => 0])]
    private string $cpPris = '0.00';

    /**
     * Solde CP au début du mois
     */
    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, options: ['default' => 0])]
    private string $cpSoldeDebut = '0.00';

    /**
     * Solde CP à la fin du mois (calculé: début + acquis - pris)
     */
    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, options: ['default' => 0])]
    private string $cpSoldeFin = '0.00';

    /**
     * Total des éléments variables (somme des montants)
     */
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, options: ['default' => 0])]
    private string $totalVariables = '0.00';

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $validatedBy = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $validatedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $exportedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $sentToAccountantAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updatedAt = null;

    /**
     * @var Collection<int, ElementVariable>
     */
    #[ORM\OneToMany(mappedBy: 'consolidation', targetEntity: ElementVariable::class)]
    private Collection $elementsVariables;

    /**
     * @var Collection<int, ConsolidationPaieHistory>
     */
    #[ORM\OneToMany(mappedBy: 'consolidation', targetEntity: ConsolidationPaieHistory::class, orphanRemoval: true)]
    #[ORM\OrderBy(['modifiedAt' => 'DESC'])]
    private Collection $history;

    public function __construct()
    {
        $this->elementsVariables = new ArrayCollection();
        $this->history = new ArrayCollection();
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

    public function getPeriod(): ?string
    {
        return $this->period;
    }

    public function setPeriod(string $period): static
    {
        $this->period = $period;
        return $this;
    }

    /**
     * Retourne l'année de la période
     */
    public function getYear(): ?int
    {
        if (!$this->period) {
            return null;
        }
        return (int) substr($this->period, 0, 4);
    }

    /**
     * Retourne le mois de la période
     */
    public function getMonth(): ?int
    {
        if (!$this->period) {
            return null;
        }
        return (int) substr($this->period, 5, 2);
    }

    /**
     * Retourne le libellé du mois (ex: "Janvier 2026")
     */
    public function getPeriodLabel(): string
    {
        if (!$this->period) {
            return '';
        }

        $months = [
            1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril',
            5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août',
            9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre'
        ];

        $month = $this->getMonth();
        $year = $this->getYear();

        return ($months[$month] ?? '') . ' ' . $year;
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

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isValidated(): bool
    {
        return $this->status === self::STATUS_VALIDATED;
    }

    public function isExported(): bool
    {
        return $this->status === self::STATUS_EXPORTED;
    }

    public function isArchived(): bool
    {
        return $this->status === self::STATUS_ARCHIVED;
    }

    public function getJoursTravailes(): string
    {
        return $this->joursTravailes;
    }

    public function setJoursTravailes(string $joursTravailes): static
    {
        $this->joursTravailes = $joursTravailes;
        return $this;
    }

    public function getJoursEvenements(): string
    {
        return $this->joursEvenements;
    }

    public function setJoursEvenements(string $joursEvenements): static
    {
        $this->joursEvenements = $joursEvenements;
        return $this;
    }

    /**
     * Retourne le total des jours travaillés (gardes + événements)
     */
    public function getTotalJoursTravailes(): float
    {
        return (float) $this->joursTravailes + (float) $this->joursEvenements;
    }

    public function getJoursAbsence(): ?array
    {
        return $this->joursAbsence;
    }

    public function setJoursAbsence(?array $joursAbsence): static
    {
        $this->joursAbsence = $joursAbsence;
        return $this;
    }

    /**
     * Retourne le total des jours d'absence
     */
    public function getTotalJoursAbsence(): float
    {
        if (!$this->joursAbsence) {
            return 0;
        }
        return array_sum($this->joursAbsence);
    }

    /**
     * Retourne les jours d'absence pour un type donné
     */
    public function getJoursAbsenceByType(string $type): float
    {
        return $this->joursAbsence[$type] ?? 0;
    }

    public function getCpAcquis(): string
    {
        return $this->cpAcquis;
    }

    public function setCpAcquis(string $cpAcquis): static
    {
        $this->cpAcquis = $cpAcquis;
        return $this;
    }

    public function getCpPris(): string
    {
        return $this->cpPris;
    }

    public function setCpPris(string $cpPris): static
    {
        $this->cpPris = $cpPris;
        return $this;
    }

    public function getCpSoldeDebut(): string
    {
        return $this->cpSoldeDebut;
    }

    public function setCpSoldeDebut(string $cpSoldeDebut): static
    {
        $this->cpSoldeDebut = $cpSoldeDebut;
        return $this;
    }

    public function getCpSoldeFin(): string
    {
        return $this->cpSoldeFin;
    }

    public function setCpSoldeFin(string $cpSoldeFin): static
    {
        $this->cpSoldeFin = $cpSoldeFin;
        return $this;
    }

    /**
     * Recalcule le solde CP fin de mois
     */
    public function recalculateCpSoldeFin(): static
    {
        $soldeFin = (float) $this->cpSoldeDebut + (float) $this->cpAcquis - (float) $this->cpPris;
        $this->cpSoldeFin = number_format($soldeFin, 2, '.', '');
        return $this;
    }

    public function getTotalVariables(): string
    {
        return $this->totalVariables;
    }

    public function setTotalVariables(string $totalVariables): static
    {
        $this->totalVariables = $totalVariables;
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

    public function getValidatedAt(): ?\DateTimeInterface
    {
        return $this->validatedAt;
    }

    public function setValidatedAt(?\DateTimeInterface $validatedAt): static
    {
        $this->validatedAt = $validatedAt;
        return $this;
    }

    public function getExportedAt(): ?\DateTimeInterface
    {
        return $this->exportedAt;
    }

    public function setExportedAt(?\DateTimeInterface $exportedAt): static
    {
        $this->exportedAt = $exportedAt;
        return $this;
    }

    public function getSentToAccountantAt(): ?\DateTimeInterface
    {
        return $this->sentToAccountantAt;
    }

    public function setSentToAccountantAt(?\DateTimeInterface $sentToAccountantAt): static
    {
        $this->sentToAccountantAt = $sentToAccountantAt;
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
     * @return Collection<int, ElementVariable>
     */
    public function getElementsVariables(): Collection
    {
        return $this->elementsVariables;
    }

    public function addElementVariable(ElementVariable $elementVariable): static
    {
        if (!$this->elementsVariables->contains($elementVariable)) {
            $this->elementsVariables->add($elementVariable);
            $elementVariable->setConsolidation($this);
        }

        return $this;
    }

    public function removeElementVariable(ElementVariable $elementVariable): static
    {
        if ($this->elementsVariables->removeElement($elementVariable)) {
            if ($elementVariable->getConsolidation() === $this) {
                $elementVariable->setConsolidation(null);
            }
        }

        return $this;
    }

    /**
     * Recalcule le total des éléments variables
     */
    public function recalculateTotalVariables(): static
    {
        $total = 0;
        foreach ($this->elementsVariables as $element) {
            $total += (float) $element->getAmount();
        }
        $this->totalVariables = number_format($total, 2, '.', '');
        return $this;
    }

    /**
     * @return Collection<int, ConsolidationPaieHistory>
     */
    public function getHistory(): Collection
    {
        return $this->history;
    }

    public function addHistory(ConsolidationPaieHistory $history): static
    {
        if (!$this->history->contains($history)) {
            $this->history->add($history);
            $history->setConsolidation($this);
        }

        return $this;
    }

    public function removeHistory(ConsolidationPaieHistory $history): static
    {
        if ($this->history->removeElement($history)) {
            if ($history->getConsolidation() === $this) {
                $history->setConsolidation(null);
            }
        }

        return $this;
    }

    /**
     * Valide la consolidation
     */
    public function validate(User $admin): static
    {
        $this->status = self::STATUS_VALIDATED;
        $this->validatedBy = $admin;
        $this->validatedAt = new \DateTime();
        return $this;
    }

    /**
     * Marque comme exporté
     */
    public function markAsExported(): static
    {
        $this->status = self::STATUS_EXPORTED;
        $this->exportedAt = new \DateTime();
        return $this;
    }

    /**
     * Marque comme envoyé au comptable
     */
    public function markAsSentToAccountant(): static
    {
        $this->sentToAccountantAt = new \DateTime();
        return $this;
    }
}
