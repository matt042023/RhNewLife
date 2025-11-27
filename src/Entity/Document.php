<?php

namespace App\Entity;

use App\Repository\DocumentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DocumentRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Document
{
    public const TYPE_CNI = 'cni';
    public const TYPE_RIB = 'rib';
    public const TYPE_DOMICILE = 'domicile';
    public const TYPE_HONORABILITE = 'honorabilite';
    public const TYPE_DIPLOME = 'diplome';
    public const TYPE_CONTRAT = 'contrat';
    public const TYPE_CONTRACT_SIGNED = 'contract_signed';
    public const TYPE_CONTRACT_AMENDMENT = 'contract_amendment';
    public const TYPE_PAYSLIP = 'payslip';
    public const TYPE_MEDICAL_CERTIFICATE = 'medical_certificate';
    public const TYPE_TRAINING_CERTIFICATE = 'training_certificate';
    public const TYPE_WORK_CERTIFICATE = 'work_certificate';
    public const TYPE_ABSENCE_JUSTIFICATION = 'absence_justification';
    public const TYPE_EXPENSE_REPORT = 'expense_report';
    public const TYPE_OTHER = 'other';

    public const STATUS_PENDING = 'pending';
    public const STATUS_VALIDATED = 'validated';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_ARCHIVED = 'archived';

    public const TYPES = [
        self::TYPE_CNI => 'Carte d\'identité',
        self::TYPE_RIB => 'RIB',
        self::TYPE_DOMICILE => 'Justificatif de domicile',
        self::TYPE_HONORABILITE => 'Attestation d\'honorabilité',
        self::TYPE_DIPLOME => 'Diplôme',
        self::TYPE_CONTRAT => 'Contrat',
        self::TYPE_CONTRACT_SIGNED => 'Contrat signé',
        self::TYPE_CONTRACT_AMENDMENT => 'Avenant',
        self::TYPE_PAYSLIP => 'Bulletin de paie',
        self::TYPE_MEDICAL_CERTIFICATE => 'Certificat mǸdical',
        self::TYPE_TRAINING_CERTIFICATE => 'Attestation de formation',
        self::TYPE_WORK_CERTIFICATE => 'Attestation de travail',
        self::TYPE_ABSENCE_JUSTIFICATION => 'Justificatif d\'absence',
        self::TYPE_EXPENSE_REPORT => 'Note de frais',
        self::TYPE_OTHER => 'Autre',
    ];

    public const REQUIRED_DOCUMENTS = [
        self::TYPE_CNI,
        self::TYPE_RIB,
        self::TYPE_DOMICILE,
        self::TYPE_HONORABILITE,
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $fileName = null;

    #[ORM\Column(length: 255)]
    private ?string $originalName = null;

    #[ORM\Column(length: 50)]
    private ?string $type = null;

    #[ORM\Column(length: 20, options: ['default' => self::STATUS_PENDING])]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $uploadedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'documents')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: Contract::class, inversedBy: 'documents')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Contract $contract = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $comment = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $mimeType = null;

    #[ORM\Column(nullable: true)]
    private ?int $fileSize = null;

    #[ORM\Column(options: ['default' => 1])]
    private int $version = 1;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $archivedAt = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $archiveReason = null;

    #[ORM\Column(nullable: true)]
    private ?int $retentionYears = null;

    #[ORM\ManyToOne(targetEntity: Absence::class)]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Absence $absence = null;

    #[ORM\ManyToOne(targetEntity: Formation::class, inversedBy: 'documents')]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Formation $formation = null;

    #[ORM\ManyToOne(targetEntity: ElementVariable::class, inversedBy: 'documents')]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?ElementVariable $elementVariable = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $validatedBy = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $validatedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $rejectedAt = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $rejectionReason = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $uploadedBy = null;

    public function __construct()
    {
        $this->uploadedAt = new \DateTime();
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

    public function getFileName(): ?string
    {
        return $this->fileName;
    }

    public function setFileName(string $fileName): static
    {
        $this->fileName = $fileName;
        return $this;
    }

    public function getOriginalName(): ?string
    {
        return $this->originalName;
    }

    public function setOriginalName(string $originalName): static
    {
        $this->originalName = $originalName;
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

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getUploadedAt(): ?\DateTimeInterface
    {
        return $this->uploadedAt;
    }

    public function setUploadedAt(\DateTimeInterface $uploadedAt): static
    {
        $this->uploadedAt = $uploadedAt;
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

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getContract(): ?Contract
    {
        return $this->contract;
    }

    public function setContract(?Contract $contract): static
    {
        $this->contract = $contract;
        return $this;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): static
    {
        $this->comment = $comment;
        return $this;
    }

    public function getMimeType(): ?string
    {
        return $this->mimeType;
    }

    public function setMimeType(?string $mimeType): static
    {
        $this->mimeType = $mimeType;
        return $this;
    }

    public function getFileSize(): ?int
    {
        return $this->fileSize;
    }

    public function setFileSize(?int $fileSize): static
    {
        $this->fileSize = $fileSize;
        return $this;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function setVersion(int $version): static
    {
        $this->version = max(1, $version);
        return $this;
    }

    public function incrementVersion(): static
    {
        $this->version++;
        return $this;
    }

    public function getFileSizeFormatted(): string
    {
        if (!$this->fileSize) {
            return 'N/A';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $size = $this->fileSize;
        $unitIndex = 0;

        while ($size >= 1024 && $unitIndex < count($units) - 1) {
            $size /= 1024;
            $unitIndex++;
        }

        return round($size, 2) . ' ' . $units[$unitIndex];
    }

    public function markAsValidated(?string $comment = null, ?User $validatedBy = null): static
    {
        $this->status = self::STATUS_VALIDATED;
        $this->validatedBy = $validatedBy;
        $this->validatedAt = new \DateTime();
        if ($comment) {
            $this->comment = $comment;
        }
        return $this;
    }

    public function markAsRejected(string $reason): static
    {
        $this->status = self::STATUS_REJECTED;
        $this->rejectionReason = $reason;
        $this->rejectedAt = new \DateTime();
        return $this;
    }

    public function isValidated(): bool
    {
        return $this->status === self::STATUS_VALIDATED;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    public function isArchived(): bool
    {
        return $this->status === self::STATUS_ARCHIVED;
    }

    public function setStatusArchived(string $reason, int $retentionYears): static
    {
        $this->status = self::STATUS_ARCHIVED;
        $this->archiveReason = $reason;
        $this->retentionYears = max(0, $retentionYears);
        $this->archivedAt = new \DateTimeImmutable();

        return $this;
    }

    public function restoreFromArchive(): static
    {
        // Restored documents go back to pending status
        $this->status = self::STATUS_PENDING;
        $this->archiveReason = null;
        $this->archivedAt = null;
        $this->retentionYears = null;

        return $this;
    }

    public function canBeDeleted(): bool
    {
        if ($this->status !== self::STATUS_ARCHIVED) {
            return false;
        }

        $expiration = $this->getExpirationDate();

        return $expiration !== null && $expiration <= new \DateTimeImmutable();
    }

    public function getExpirationDate(): ?\DateTimeImmutable
    {
        if (!$this->archivedAt || $this->retentionYears === null) {
            return null;
        }

        $interval = sprintf('P%dY', $this->retentionYears);

        return $this->archivedAt->add(new \DateInterval($interval));
    }

    public function getArchivedAt(): ?\DateTimeImmutable
    {
        return $this->archivedAt;
    }

    public function setArchivedAt(?\DateTimeImmutable $archivedAt): static
    {
        $this->archivedAt = $archivedAt;
        return $this;
    }

    public function getArchiveReason(): ?string
    {
        return $this->archiveReason;
    }

    public function setArchiveReason(?string $archiveReason): static
    {
        $this->archiveReason = $archiveReason;
        return $this;
    }

    public function getRetentionYears(): ?int
    {
        return $this->retentionYears;
    }

    public function setRetentionYears(?int $retentionYears): static
    {
        $this->retentionYears = $retentionYears;
        return $this;
    }

    public function getAbsence(): ?Absence
    {
        return $this->absence;
    }

    public function setAbsence(?Absence $absence): static
    {
        $this->absence = $absence;
        return $this;
    }

    public function getFormation(): ?Formation
    {
        return $this->formation;
    }

    public function setFormation(?Formation $formation): static
    {
        $this->formation = $formation;
        return $this;
    }

    public function getElementVariable(): ?ElementVariable
    {
        return $this->elementVariable;
    }

    public function setElementVariable(?ElementVariable $elementVariable): static
    {
        $this->elementVariable = $elementVariable;
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

    public function getUploadedBy(): ?User
    {
        return $this->uploadedBy;
    }

    public function setUploadedBy(?User $uploadedBy): static
    {
        $this->uploadedBy = $uploadedBy;
        return $this;
    }

    public function getRejectedAt(): ?\DateTimeInterface
    {
        return $this->rejectedAt;
    }

    public function setRejectedAt(?\DateTimeInterface $rejectedAt): static
    {
        $this->rejectedAt = $rejectedAt;
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
}
