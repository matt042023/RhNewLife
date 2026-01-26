<?php

namespace App\Entity;

use App\Repository\NotificationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: NotificationRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(name: 'idx_notification_user', columns: ['cible_user_id'])]
#[ORM\Index(name: 'idx_notification_date', columns: ['date_envoi'])]
#[ORM\Index(name: 'idx_notification_lu', columns: ['lu'])]
class Notification
{
    // Type constants
    public const TYPE_ALERTE = 'alerte';
    public const TYPE_ACTION = 'action';
    public const TYPE_INFO = 'info';

    public const TYPES = [
        self::TYPE_ALERTE => 'Alerte',
        self::TYPE_ACTION => 'Action requise',
        self::TYPE_INFO => 'Information',
    ];

    // Source events for deduplication (R1)
    public const SOURCE_ABSENCE_CREATED = 'absence_created';
    public const SOURCE_ABSENCE_VALIDATED = 'absence_validated';
    public const SOURCE_ABSENCE_REJECTED = 'absence_rejected';
    public const SOURCE_CONTRACT_CREATED = 'contract_created';
    public const SOURCE_CONTRACT_SIGNED = 'contract_signed';
    public const SOURCE_VISITE_MEDICALE_EXPIRING = 'visite_medicale_expiring';
    public const SOURCE_DOCUMENT_PENDING = 'document_pending';
    public const SOURCE_PAYROLL_VALIDATED = 'payroll_validated';
    public const SOURCE_APPOINTMENT_CREATED = 'appointment_created';
    public const SOURCE_MESSAGE_RECEIVED = 'message_received';
    public const SOURCE_ANNONCE_PUBLISHED = 'annonce_published';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20)]
    private string $type = self::TYPE_INFO;

    #[ORM\Column(length: 255)]
    private string $titre;

    #[ORM\Column(type: Types::TEXT)]
    private string $message;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $lien = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?User $cibleUser = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $rolesCible = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $dateEnvoi;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    private bool $lu = false;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $luAt = null;

    // For deduplication (R1: non dupliquees)
    #[ORM\Column(length: 50, nullable: true)]
    private ?string $sourceEvent = null;

    #[ORM\Column(nullable: true)]
    private ?int $sourceEntityId = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $createdAt;

    public function __construct()
    {
        $this->dateEnvoi = new \DateTime();
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): string
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

    public function getTitre(): string
    {
        return $this->titre;
    }

    public function setTitre(string $titre): static
    {
        $this->titre = $titre;
        return $this;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function setMessage(string $message): static
    {
        $this->message = $message;
        return $this;
    }

    public function getLien(): ?string
    {
        return $this->lien;
    }

    public function setLien(?string $lien): static
    {
        $this->lien = $lien;
        return $this;
    }

    public function getCibleUser(): ?User
    {
        return $this->cibleUser;
    }

    public function setCibleUser(?User $cibleUser): static
    {
        $this->cibleUser = $cibleUser;
        return $this;
    }

    public function getRolesCible(): ?array
    {
        return $this->rolesCible;
    }

    public function setRolesCible(?array $rolesCible): static
    {
        $this->rolesCible = $rolesCible;
        return $this;
    }

    public function getDateEnvoi(): \DateTimeInterface
    {
        return $this->dateEnvoi;
    }

    public function setDateEnvoi(\DateTimeInterface $dateEnvoi): static
    {
        $this->dateEnvoi = $dateEnvoi;
        return $this;
    }

    public function isLu(): bool
    {
        return $this->lu;
    }

    public function setLu(bool $lu): static
    {
        $this->lu = $lu;
        return $this;
    }

    public function getLuAt(): ?\DateTimeInterface
    {
        return $this->luAt;
    }

    public function setLuAt(?\DateTimeInterface $luAt): static
    {
        $this->luAt = $luAt;
        return $this;
    }

    public function getSourceEvent(): ?string
    {
        return $this->sourceEvent;
    }

    public function setSourceEvent(?string $sourceEvent): static
    {
        $this->sourceEvent = $sourceEvent;
        return $this;
    }

    public function getSourceEntityId(): ?int
    {
        return $this->sourceEntityId;
    }

    public function setSourceEntityId(?int $sourceEntityId): static
    {
        $this->sourceEntityId = $sourceEntityId;
        return $this;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    // Helper methods
    public function isUnread(): bool
    {
        return !$this->lu;
    }

    public function markAsRead(): static
    {
        $this->lu = true;
        $this->luAt = new \DateTime();
        return $this;
    }

    public function isAlerte(): bool
    {
        return $this->type === self::TYPE_ALERTE;
    }

    public function isAction(): bool
    {
        return $this->type === self::TYPE_ACTION;
    }

    public function isInfo(): bool
    {
        return $this->type === self::TYPE_INFO;
    }
}
