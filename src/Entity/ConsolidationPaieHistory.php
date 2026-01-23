<?php

namespace App\Entity;

use App\Repository\ConsolidationPaieHistoryRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ConsolidationPaieHistoryRepository::class)]
#[ORM\HasLifecycleCallbacks]
class ConsolidationPaieHistory
{
    public const ACTION_CREATED = 'created';
    public const ACTION_UPDATED = 'updated';
    public const ACTION_VALIDATED = 'validated';
    public const ACTION_EXPORTED = 'exported';
    public const ACTION_CORRECTION = 'correction';
    public const ACTION_REOPENED = 'reopened';

    public const ACTIONS = [
        self::ACTION_CREATED => 'Création',
        self::ACTION_UPDATED => 'Modification',
        self::ACTION_VALIDATED => 'Validation',
        self::ACTION_EXPORTED => 'Export',
        self::ACTION_CORRECTION => 'Correction',
        self::ACTION_REOPENED => 'Réouverture',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ConsolidationPaie::class, inversedBy: 'history')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?ConsolidationPaie $consolidation = null;

    #[ORM\Column(length: 50)]
    private ?string $action = null;

    /**
     * Champ modifié (ex: "joursTravailes", "cpAcquis", etc.)
     */
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $field = null;

    /**
     * Ancienne valeur (JSON encodé pour les valeurs complexes)
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $oldValue = null;

    /**
     * Nouvelle valeur (JSON encodé pour les valeurs complexes)
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $newValue = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $modifiedBy = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $modifiedAt = null;

    /**
     * Commentaire optionnel (justification de la modification)
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $comment = null;

    public function __construct()
    {
        $this->modifiedAt = new \DateTime();
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->modifiedAt ??= new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getConsolidation(): ?ConsolidationPaie
    {
        return $this->consolidation;
    }

    public function setConsolidation(?ConsolidationPaie $consolidation): static
    {
        $this->consolidation = $consolidation;
        return $this;
    }

    public function getAction(): ?string
    {
        return $this->action;
    }

    public function setAction(string $action): static
    {
        $this->action = $action;
        return $this;
    }

    public function getActionLabel(): string
    {
        return self::ACTIONS[$this->action] ?? $this->action;
    }

    public function getField(): ?string
    {
        return $this->field;
    }

    public function setField(?string $field): static
    {
        $this->field = $field;
        return $this;
    }

    /**
     * Retourne le libellé du champ modifié
     */
    public function getFieldLabel(): string
    {
        $labels = [
            'joursTravailes' => 'Jours travaillés (gardes)',
            'joursEvenements' => 'Jours travaillés (événements)',
            'joursAbsence' => 'Jours d\'absence',
            'cpAcquis' => 'CP acquis',
            'cpPris' => 'CP pris',
            'cpSoldeDebut' => 'Solde CP début',
            'cpSoldeFin' => 'Solde CP fin',
            'totalVariables' => 'Total variables',
            'status' => 'Statut',
        ];

        return $labels[$this->field] ?? $this->field ?? '';
    }

    public function getOldValue(): ?string
    {
        return $this->oldValue;
    }

    public function setOldValue(mixed $oldValue): static
    {
        if (is_array($oldValue) || is_object($oldValue)) {
            $this->oldValue = json_encode($oldValue);
        } else {
            $this->oldValue = $oldValue !== null ? (string) $oldValue : null;
        }
        return $this;
    }

    /**
     * Retourne l'ancienne valeur décodée
     */
    public function getOldValueDecoded(): mixed
    {
        if ($this->oldValue === null) {
            return null;
        }

        $decoded = json_decode($this->oldValue, true);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : $this->oldValue;
    }

    public function getNewValue(): ?string
    {
        return $this->newValue;
    }

    public function setNewValue(mixed $newValue): static
    {
        if (is_array($newValue) || is_object($newValue)) {
            $this->newValue = json_encode($newValue);
        } else {
            $this->newValue = $newValue !== null ? (string) $newValue : null;
        }
        return $this;
    }

    /**
     * Retourne la nouvelle valeur décodée
     */
    public function getNewValueDecoded(): mixed
    {
        if ($this->newValue === null) {
            return null;
        }

        $decoded = json_decode($this->newValue, true);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : $this->newValue;
    }

    public function getModifiedBy(): ?User
    {
        return $this->modifiedBy;
    }

    public function setModifiedBy(?User $modifiedBy): static
    {
        $this->modifiedBy = $modifiedBy;
        return $this;
    }

    public function getModifiedAt(): ?\DateTimeInterface
    {
        return $this->modifiedAt;
    }

    public function setModifiedAt(\DateTimeInterface $modifiedAt): static
    {
        $this->modifiedAt = $modifiedAt;
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

    /**
     * Factory method pour créer une entrée d'historique
     */
    public static function create(
        ConsolidationPaie $consolidation,
        string $action,
        User $modifiedBy,
        ?string $field = null,
        mixed $oldValue = null,
        mixed $newValue = null,
        ?string $comment = null
    ): self {
        $history = new self();
        $history->setConsolidation($consolidation);
        $history->setAction($action);
        $history->setModifiedBy($modifiedBy);
        $history->setField($field);
        $history->setOldValue($oldValue);
        $history->setNewValue($newValue);
        $history->setComment($comment);

        return $history;
    }
}
