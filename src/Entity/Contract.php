<?php

namespace App\Entity;

use App\Repository\ContractRepository;
use App\Validator\UniqueActiveContract;
use App\Validator\ContractDatesCoherent;
use App\Validator\ContractType;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ContractRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[UniqueActiveContract]
#[ContractDatesCoherent]
class Contract
{
    // Types de contrat
    public const TYPE_CDI = 'CDI';
    public const TYPE_CDD = 'CDD';
    public const TYPE_STAGE = 'Stage';
    public const TYPE_ALTERNANCE = 'Alternance';
    public const TYPE_OTHER = 'Autre';

    public const TYPES = [
        self::TYPE_CDI => 'CDI',
        self::TYPE_CDD => 'CDD',
        self::TYPE_STAGE => 'Stage',
        self::TYPE_ALTERNANCE => 'Alternance',
        self::TYPE_OTHER => 'Autre',
    ];

    // Statuts du contrat (WF09)
    public const STATUS_DRAFT = 'DRAFT';
    public const STATUS_PENDING_SIGNATURE = 'PENDING_SIGNATURE';
    public const STATUS_SIGNED_PENDING_VALIDATION = 'SIGNED_PENDING_VALIDATION';
    public const STATUS_ACTIVE = 'ACTIVE';
    public const STATUS_REPLACED = 'REPLACED';
    public const STATUS_ARCHIVED = 'ARCHIVED';
    public const STATUS_CANCELLED = 'CANCELLED';
    public const STATUS_SUSPENDED = 'SUSPENDED';
    public const STATUS_TERMINATED = 'TERMINATED';

    public const STATUSES = [
        self::STATUS_DRAFT => 'Brouillon',
        self::STATUS_PENDING_SIGNATURE => 'En attente de signature',
        self::STATUS_SIGNED_PENDING_VALIDATION => 'Signé - En attente de validation',
        self::STATUS_ACTIVE => 'Actif',
        self::STATUS_REPLACED => 'Remplacé',
        self::STATUS_ARCHIVED => 'Archivé',
        self::STATUS_CANCELLED => 'Annulé',
        self::STATUS_SUSPENDED => 'Suspendu',
        self::STATUS_TERMINATED => 'Résilié',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'contracts')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank(message: 'Le type de contrat est obligatoire.')]
    #[ContractType]
    private ?string $type = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    #[Assert\NotNull(message: 'La date de début est obligatoire.')]
    private ?\DateTimeInterface $startDate = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $endDate = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $essaiEndDate = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $baseSalary = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $prime = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $workingDays = null;

    #[ORM\ManyToOne(targetEntity: Villa::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Villa $villa = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 3, scale: 2, options: ['default' => '1.00'])]
    private string $activityRate = '1.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, nullable: true)]
    private ?string $weeklyHours = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $mutuelle = false;

    #[ORM\Column(options: ['default' => false])]
    private bool $prevoyance = false;

    #[ORM\Column(length: 30, options: ['default' => self::STATUS_DRAFT])]
    private string $status = self::STATUS_DRAFT;

    #[ORM\Column(options: ['default' => 1])]
    private int $version = 1;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'amendments')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?self $parentContract = null;

    /**
     * @var Collection<int, Contract>
     */
    #[ORM\OneToMany(targetEntity: self::class, mappedBy: 'parentContract')]
    private Collection $amendments;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $terminationReason = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $createdBy = null;

    #[ORM\ManyToOne(targetEntity: TemplateContrat::class, inversedBy: 'contracts')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?TemplateContrat $template = null;

    #[ORM\Column(type: Types::STRING, length: 500, nullable: true)]
    private ?string $draftFileUrl = null;

    #[ORM\Column(type: Types::STRING, length: 500, nullable: true)]
    private ?string $signedFileUrl = null;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true, unique: true)]
    private ?string $signatureToken = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $tokenExpiresAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $validatedBy = null;

    #[ORM\Column(type: Types::STRING, length: 45, nullable: true)]
    private ?string $signatureIp = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $signatureUserAgent = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $validatedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $signedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $terminatedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updatedAt = null;

    /**
     * @var Collection<int, Document>
     */
    #[ORM\OneToMany(targetEntity: Document::class, mappedBy: 'contract')]
    private Collection $documents;

    public function __construct()
    {
        $this->amendments = new ArrayCollection();
        $this->documents = new ArrayCollection();
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
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

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function getTypeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function getStartDate(): ?\DateTimeInterface
    {
        return $this->startDate;
    }

    public function setStartDate(\DateTimeInterface $startDate): static
    {
        $this->startDate = $startDate;
        return $this;
    }

    public function getEndDate(): ?\DateTimeInterface
    {
        return $this->endDate;
    }

    public function setEndDate(?\DateTimeInterface $endDate): static
    {
        $this->endDate = $endDate;
        return $this;
    }

    public function getEssaiEndDate(): ?\DateTimeInterface
    {
        return $this->essaiEndDate;
    }

    public function setEssaiEndDate(?\DateTimeInterface $essaiEndDate): static
    {
        $this->essaiEndDate = $essaiEndDate;
        return $this;
    }

    public function getBaseSalary(): ?string
    {
        return $this->baseSalary;
    }

    public function setBaseSalary(string $baseSalary): static
    {
        $this->baseSalary = $baseSalary;
        return $this;
    }

    public function getPrime(): ?string
    {
        return $this->prime;
    }

    public function setPrime(?string $prime): static
    {
        $this->prime = $prime;
        return $this;
    }

    public function getWorkingDays(): ?array
    {
        return $this->workingDays;
    }

    public function setWorkingDays(?array $workingDays): static
    {
        $this->workingDays = $workingDays;
        return $this;
    }

    public function getVilla(): ?Villa
    {
        return $this->villa;
    }

    public function setVilla(?Villa $villa): static
    {
        $this->villa = $villa;
        return $this;
    }

    public function getActivityRate(): string
    {
        return $this->activityRate;
    }

    public function setActivityRate(string $activityRate): static
    {
        $this->activityRate = $activityRate;
        return $this;
    }

    public function getWeeklyHours(): ?string
    {
        return $this->weeklyHours;
    }

    public function setWeeklyHours(?string $weeklyHours): static
    {
        $this->weeklyHours = $weeklyHours;
        return $this;
    }

    public function isMutuelle(): bool
    {
        return $this->mutuelle;
    }

    public function setMutuelle(bool $mutuelle): static
    {
        $this->mutuelle = $mutuelle;
        return $this;
    }

    public function isPrevoyance(): bool
    {
        return $this->prevoyance;
    }

    public function setPrevoyance(bool $prevoyance): static
    {
        $this->prevoyance = $prevoyance;
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

    public function getVersion(): int
    {
        return $this->version;
    }

    public function setVersion(int $version): static
    {
        $this->version = $version;
        return $this;
    }

    public function getParentContract(): ?self
    {
        return $this->parentContract;
    }

    public function setParentContract(?self $parentContract): static
    {
        $this->parentContract = $parentContract;
        return $this;
    }

    /**
     * @return Collection<int, Contract>
     */
    public function getAmendments(): Collection
    {
        return $this->amendments;
    }

    public function addAmendment(self $amendment): static
    {
        if (!$this->amendments->contains($amendment)) {
            $this->amendments->add($amendment);
            $amendment->setParentContract($this);
        }

        return $this;
    }

    public function removeAmendment(self $amendment): static
    {
        if ($this->amendments->removeElement($amendment)) {
            if ($amendment->getParentContract() === $this) {
                $amendment->setParentContract(null);
            }
        }

        return $this;
    }

    public function getTerminationReason(): ?string
    {
        return $this->terminationReason;
    }

    public function setTerminationReason(?string $terminationReason): static
    {
        $this->terminationReason = $terminationReason;
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

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $createdBy): static
    {
        $this->createdBy = $createdBy;
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

    public function getSignedAt(): ?\DateTimeInterface
    {
        return $this->signedAt;
    }

    public function setSignedAt(?\DateTimeInterface $signedAt): static
    {
        $this->signedAt = $signedAt;
        return $this;
    }

    public function getTerminatedAt(): ?\DateTimeInterface
    {
        return $this->terminatedAt;
    }

    public function setTerminatedAt(?\DateTimeInterface $terminatedAt): static
    {
        $this->terminatedAt = $terminatedAt;
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
            $document->setContract($this);
        }

        return $this;
    }

    public function removeDocument(Document $document): static
    {
        if ($this->documents->removeElement($document)) {
            if ($document->getContract() === $this) {
                $document->setContract(null);
            }
        }

        return $this;
    }

    // Helper methods

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isSigned(): bool
    {
        return in_array($this->status, [
            self::STATUS_SIGNED_PENDING_VALIDATION,
            self::STATUS_ACTIVE,
        ]);
    }

    public function isTerminated(): bool
    {
        return $this->status === self::STATUS_TERMINATED;
    }

    public function isCDD(): bool
    {
        return $this->type === self::TYPE_CDD;
    }

    public function isCDI(): bool
    {
        return $this->type === self::TYPE_CDI;
    }

    /**
     * Calcule la durée du contrat en jours
     */
    public function getDuration(): ?int
    {
        if (!$this->startDate || !$this->endDate) {
            return null;
        }

        return $this->startDate->diff($this->endDate)->days;
    }

    /**
     * Vérifie si le contrat est un avenant
     */
    public function isAmendment(): bool
    {
        return $this->parentContract !== null;
    }

    /**
     * Retourne le numéro de version lisible (v1, v2, etc.)
     */
    public function getVersionLabel(): string
    {
        return 'v' . $this->version;
    }

    /**
     * Calcule le salaire net estimé (formule simplifiée : 78% du brut)
     */
    public function getEstimatedNetSalary(): ?string
    {
        if (!$this->baseSalary) {
            return null;
        }

        return (string) round((float) $this->baseSalary * 0.78, 2);
    }

    // Nouveaux getters/setters pour WF09

    public function getTemplate(): ?TemplateContrat
    {
        return $this->template;
    }

    public function setTemplate(?TemplateContrat $template): static
    {
        $this->template = $template;
        return $this;
    }

    public function getDraftFileUrl(): ?string
    {
        return $this->draftFileUrl;
    }

    public function setDraftFileUrl(?string $draftFileUrl): static
    {
        $this->draftFileUrl = $draftFileUrl;
        return $this;
    }

    public function getSignedFileUrl(): ?string
    {
        return $this->signedFileUrl;
    }

    public function setSignedFileUrl(?string $signedFileUrl): static
    {
        $this->signedFileUrl = $signedFileUrl;
        return $this;
    }

    public function getSignatureToken(): ?string
    {
        return $this->signatureToken;
    }

    public function setSignatureToken(?string $signatureToken): static
    {
        $this->signatureToken = $signatureToken;
        return $this;
    }

    public function getTokenExpiresAt(): ?\DateTimeInterface
    {
        return $this->tokenExpiresAt;
    }

    public function setTokenExpiresAt(?\DateTimeInterface $tokenExpiresAt): static
    {
        $this->tokenExpiresAt = $tokenExpiresAt;
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

    public function getSignatureIp(): ?string
    {
        return $this->signatureIp;
    }

    public function setSignatureIp(?string $signatureIp): static
    {
        $this->signatureIp = $signatureIp;
        return $this;
    }

    public function getSignatureUserAgent(): ?string
    {
        return $this->signatureUserAgent;
    }

    public function setSignatureUserAgent(?string $signatureUserAgent): static
    {
        $this->signatureUserAgent = $signatureUserAgent;
        return $this;
    }

    // Nouvelles méthodes helper pour WF09

    public function isPendingSignature(): bool
    {
        return $this->status === self::STATUS_PENDING_SIGNATURE;
    }

    public function isSignedPendingValidation(): bool
    {
        return $this->status === self::STATUS_SIGNED_PENDING_VALIDATION;
    }

    public function isReplaced(): bool
    {
        return $this->status === self::STATUS_REPLACED;
    }

    public function isArchived(): bool
    {
        return $this->status === self::STATUS_ARCHIVED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    /**
     * Vérifie si le contrat peut être signé par l'employé
     */
    public function canBeSigned(): bool
    {
        return $this->status === self::STATUS_PENDING_SIGNATURE
            && $this->signatureToken !== null
            && $this->isSignatureTokenValid();
    }

    /**
     * Vérifie si le contrat peut être validé par l'admin
     */
    public function canBeValidated(): bool
    {
        return $this->status === self::STATUS_SIGNED_PENDING_VALIDATION;
    }

    /**
     * Vérifie si le contrat peut être annulé
     */
    public function canBeCancelled(): bool
    {
        return in_array($this->status, [
            self::STATUS_DRAFT,
            self::STATUS_PENDING_SIGNATURE,
            self::STATUS_SIGNED_PENDING_VALIDATION,
        ]);
    }

    /**
     * Vérifie si le token de signature est valide (non expiré)
     */
    public function isSignatureTokenValid(): bool
    {
        if (!$this->signatureToken || !$this->tokenExpiresAt) {
            return false;
        }

        return $this->tokenExpiresAt > new \DateTime();
    }

    /**
     * Retourne les jours travaillés formatés pour affichage
     */
    public function getWorkingDaysFormatted(): string
    {
        if (!$this->workingDays || empty($this->workingDays)) {
            return 'Non défini';
        }

        $days = [
            'monday' => 'Lundi',
            'tuesday' => 'Mardi',
            'wednesday' => 'Mercredi',
            'thursday' => 'Jeudi',
            'friday' => 'Vendredi',
            'saturday' => 'Samedi',
            'sunday' => 'Dimanche',
        ];

        $formatted = [];
        foreach ($this->workingDays as $day) {
            if (isset($days[$day])) {
                $formatted[] = $days[$day];
            }
        }

        return implode(', ', $formatted);
    }
}
