<?php

namespace App\Entity;

use App\Repository\ElementVariableRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ElementVariableRepository::class)]
#[ORM\HasLifecycleCallbacks]
class ElementVariable
{
    public const CATEGORY_PRIME = 'prime';
    public const CATEGORY_PRIME_EXCEPTIONNELLE = 'prime_exceptionnelle';
    public const CATEGORY_AVANCE = 'avance';
    public const CATEGORY_ACOMPTE = 'acompte';
    public const CATEGORY_FRAIS = 'frais';
    public const CATEGORY_INDEMNITE_TRANSPORT = 'indemnite_transport';
    public const CATEGORY_RETENUE = 'retenue';

    public const CATEGORIES = [
        self::CATEGORY_PRIME => 'Prime',
        self::CATEGORY_PRIME_EXCEPTIONNELLE => 'Prime exceptionnelle',
        self::CATEGORY_AVANCE => 'Avance',
        self::CATEGORY_ACOMPTE => 'Acompte',
        self::CATEGORY_FRAIS => 'Remboursement de frais',
        self::CATEGORY_INDEMNITE_TRANSPORT => 'Indemnité transport',
        self::CATEGORY_RETENUE => 'Retenue',
    ];

    public const STATUS_DRAFT = 'draft';
    public const STATUS_VALIDATED = 'validated';

    public const STATUSES = [
        self::STATUS_DRAFT => 'Brouillon',
        self::STATUS_VALIDATED => 'Validé',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(length: 255)]
    private ?string $label = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $amount = null;

    #[ORM\Column(length: 7)]
    private ?string $period = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 50, options: ['default' => self::CATEGORY_PRIME])]
    private string $category = self::CATEGORY_PRIME;

    #[ORM\Column(length: 20, options: ['default' => self::STATUS_DRAFT])]
    private string $status = self::STATUS_DRAFT;

    #[ORM\ManyToOne(targetEntity: ConsolidationPaie::class, inversedBy: 'elementsVariables')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?ConsolidationPaie $consolidation = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $validatedBy = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $validatedAt = null;

    /**
     * @var Collection<int, Document>
     */
    #[ORM\OneToMany(mappedBy: 'elementVariable', targetEntity: Document::class)]
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

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(string $label): static
    {
        $this->label = $label;
        return $this;
    }

    public function getAmount(): ?string
    {
        return $this->amount;
    }

    public function setAmount(string $amount): static
    {
        $this->amount = $amount;
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
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
            $document->setElementVariable($this);
        }

        return $this;
    }

    public function removeDocument(Document $document): static
    {
        if ($this->documents->removeElement($document)) {
            if ($document->getElementVariable() === $this) {
                $document->setElementVariable(null);
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

    public function getCategory(): string
    {
        return $this->category;
    }

    public function setCategory(string $category): static
    {
        $this->category = $category;
        return $this;
    }

    public function getCategoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? $this->category;
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

    public function getConsolidation(): ?ConsolidationPaie
    {
        return $this->consolidation;
    }

    public function setConsolidation(?ConsolidationPaie $consolidation): static
    {
        $this->consolidation = $consolidation;
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

    /**
     * Valide l'élément variable
     */
    public function validate(User $admin): static
    {
        $this->status = self::STATUS_VALIDATED;
        $this->validatedBy = $admin;
        $this->validatedAt = new \DateTime();
        return $this;
    }

    /**
     * Vérifie si le montant est positif (prime, frais, etc.)
     */
    public function isPositiveAmount(): bool
    {
        return (float) $this->amount >= 0;
    }

    /**
     * Vérifie si le montant est négatif (retenue)
     */
    public function isNegativeAmount(): bool
    {
        return (float) $this->amount < 0;
    }
}
