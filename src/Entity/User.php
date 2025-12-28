<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
#[ORM\HasLifecycleCallbacks]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    public const STATUS_INVITED = 'invited';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_ARCHIVED = 'archived';
    public const STATUS_ONBOARDING = 'onboarding';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private ?string $email = null;

    /**
     * @var list<string> The user roles
     */
    #[ORM\Column]
    private array $roles = [];

    /**
     * @var string The hashed password
     */
    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column(length: 100)]
    private ?string $firstName = null;

    #[ORM\Column(length: 100)]
    private ?string $lastName = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $address = null;

    #[ORM\Column(length: 20, options: ['default' => self::STATUS_INVITED])]
    private string $status = self::STATUS_INVITED;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $position = null;

    #[ORM\ManyToOne(targetEntity: Villa::class, inversedBy: 'users')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'RESTRICT')]
    private ?Villa $villa = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $familyStatus = null;

    #[ORM\Column(nullable: true)]
    private ?int $children = null;

    #[ORM\Column(length: 34, nullable: true)]
    private ?string $iban = null;

    #[ORM\Column(length: 11, nullable: true)]
    private ?string $bic = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $cguAcceptedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $submittedAt = null;

    #[ORM\Column(length: 20, unique: true, nullable: true)]
    #[\App\Validator\MatriculeFormat]
    private ?string $matricule = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $hiringDate = null;

    /**
     * @var Collection<int, Document>
     */
    #[ORM\OneToMany(targetEntity: Document::class, mappedBy: 'user', orphanRemoval: true)]
    private Collection $documents;

    /**
     * @var Collection<int, Contract>
     */
    #[ORM\OneToMany(targetEntity: Contract::class, mappedBy: 'user', orphanRemoval: true)]
    private Collection $contracts;

    /**
     * @var Collection<int, VisiteMedicale>
     */
    #[ORM\OneToMany(targetEntity: VisiteMedicale::class, mappedBy: 'user', orphanRemoval: true)]
    private Collection $visitesMedicales;

    /**
     * @var Collection<int, Astreinte>
     */
    #[ORM\OneToMany(targetEntity: Astreinte::class, mappedBy: 'educateur')]
    private Collection $astreintes;

    #[ORM\OneToOne(mappedBy: 'user', cascade: ['persist', 'remove'])]
    private ?Health $health = null;

    public function __construct()
    {
        $this->documents = new ArrayCollection();
        $this->contracts = new ArrayCollection();
        $this->visitesMedicales = new ArrayCollection();
        $this->astreintes = new ArrayCollection();
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
        $this->health = new Health();
        $this->health->setUser($this);
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

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): static
    {
        $this->firstName = $firstName;
        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): static
    {
        $this->lastName = $lastName;
        return $this;
    }

    public function getFullName(): string
    {
        return trim($this->firstName . ' ' . $this->lastName);
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): static
    {
        $this->phone = $phone;
        return $this;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(?string $address): static
    {
        $this->address = $address;
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

    public function getPosition(): ?string
    {
        return $this->position;
    }

    public function setPosition(?string $position): static
    {
        $this->position = $position;
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

    public function getFamilyStatus(): ?string
    {
        return $this->familyStatus;
    }

    public function setFamilyStatus(?string $familyStatus): static
    {
        $this->familyStatus = $familyStatus;
        return $this;
    }

    public function getChildren(): ?int
    {
        return $this->children;
    }

    public function setChildren(?int $children): static
    {
        $this->children = $children;
        return $this;
    }

    public function getIban(): ?string
    {
        return $this->iban;
    }

    public function setIban(?string $iban): static
    {
        $this->iban = $iban;
        return $this;
    }

    public function getBic(): ?string
    {
        return $this->bic;
    }

    public function setBic(?string $bic): static
    {
        $this->bic = $bic;
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

    public function getCguAcceptedAt(): ?\DateTimeInterface
    {
        return $this->cguAcceptedAt;
    }

    public function setCguAcceptedAt(?\DateTimeInterface $cguAcceptedAt): static
    {
        $this->cguAcceptedAt = $cguAcceptedAt;
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
            $document->setUser($this);
        }

        return $this;
    }

    public function removeDocument(Document $document): static
    {
        if ($this->documents->removeElement($document)) {
            if ($document->getUser() === $this) {
                $document->setUser(null);
            }
        }

        return $this;
    }

    public function getSubmittedAt(): ?\DateTimeInterface
    {
        return $this->submittedAt;
    }

    public function setSubmittedAt(?\DateTimeInterface $submittedAt): static
    {
        $this->submittedAt = $submittedAt;

        return $this;
    }

    public function isSubmitted(): bool
    {
        return $this->submittedAt !== null;
    }

    public function getMatricule(): ?string
    {
        return $this->matricule;
    }

    public function setMatricule(?string $matricule): static
    {
        $this->matricule = $matricule;
        return $this;
    }

    public function getHiringDate(): ?\DateTimeInterface
    {
        return $this->hiringDate;
    }

    public function setHiringDate(?\DateTimeInterface $hiringDate): static
    {
        $this->hiringDate = $hiringDate;
        return $this;
    }

    /**
     * @return Collection<int, Contract>
     */
    public function getContracts(): Collection
    {
        return $this->contracts;
    }

    public function addContract(Contract $contract): static
    {
        if (!$this->contracts->contains($contract)) {
            $this->contracts->add($contract);
            $contract->setUser($this);
        }

        return $this;
    }

    public function removeContract(Contract $contract): static
    {
        if ($this->contracts->removeElement($contract)) {
            if ($contract->getUser() === $this) {
                $contract->setUser(null);
            }
        }

        return $this;
    }

    /**
     * Retourne le contrat actif de l'utilisateur (s'il existe)
     */
    public function getActiveContract(): ?Contract
    {
        foreach ($this->contracts as $contract) {
            if ($contract->isActive() || $contract->isSigned()) {
                return $contract;
            }
        }

        return null;
    }

    /**
     * Vérifie si l'utilisateur a tous les documents obligatoires
     */
    public function hasCompleteDocuments(): bool
    {
        $uploadedTypes = [];
        foreach ($this->documents as $document) {
            $uploadedTypes[] = $document->getType();
        }

        foreach (Document::REQUIRED_DOCUMENTS as $requiredType) {
            if (!in_array($requiredType, $uploadedTypes, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Retourne la liste des documents manquants
     */
    public function getMissingDocuments(): array
    {
        $uploadedTypes = [];
        foreach ($this->documents as $document) {
            $uploadedTypes[] = $document->getType();
        }

        $missing = [];
        foreach (Document::REQUIRED_DOCUMENTS as $requiredType) {
            if (!in_array($requiredType, $uploadedTypes, true)) {
                $missing[] = $requiredType;
            }
        }

        return $missing;
    }

    /**
     * Ensure the session doesn't contain actual password hashes by CRC32C-hashing them, as supported since Symfony 7.3.
     */
    public function __serialize(): array
    {
        $data = (array) $this;
        $data["\0" . self::class . "\0password"] = hash('crc32c', $this->password);

        return $data;
    }

    public function getHealth(): ?Health
    {
        return $this->health;
    }

    public function setHealth(Health $health): static
    {
        // set the owning side of the relation if necessary
        if ($health->getUser() !== $this) {
            $health->setUser($this);
        }

        $this->health = $health;

        return $this;
    }

    /**
     * @return Collection<int, VisiteMedicale>
     */
    public function getVisitesMedicales(): Collection
    {
        return $this->visitesMedicales;
    }

    public function addVisiteMedicale(VisiteMedicale $visiteMedicale): static
    {
        if (!$this->visitesMedicales->contains($visiteMedicale)) {
            $this->visitesMedicales->add($visiteMedicale);
            $visiteMedicale->setUser($this);
        }

        return $this;
    }

    public function removeVisiteMedicale(VisiteMedicale $visiteMedicale): static
    {
        if ($this->visitesMedicales->removeElement($visiteMedicale)) {
            if ($visiteMedicale->getUser() === $this) {
                $visiteMedicale->setUser(null);
            }
        }

        return $this;
    }

    /**
     * Get latest medical visit
     */
    public function getLatestVisiteMedicale(): ?VisiteMedicale
    {
        if ($this->visitesMedicales->isEmpty()) {
            return null;
        }

        $visites = $this->visitesMedicales->toArray();
        usort($visites, fn($a, $b) => $b->getVisitDate() <=> $a->getVisitDate());

        return $visites[0] ?? null;
    }

    /**
     * Check if medical visit is up to date (not expired)
     */
    public function hasMedicalVisitUpToDate(): bool
    {
        $latest = $this->getLatestVisiteMedicale();

        if (!$latest) {
            return false;
        }

        return !$latest->isExpired();
    }

    /**
     * @return Collection<int, Astreinte>
     */
    public function getAstreintes(): Collection
    {
        return $this->astreintes;
    }

    public function addAstreinte(Astreinte $astreinte): static
    {
        if (!$this->astreintes->contains($astreinte)) {
            $this->astreintes->add($astreinte);
            $astreinte->setEducateur($this);
        }

        return $this;
    }

    public function removeAstreinte(Astreinte $astreinte): static
    {
        if ($this->astreintes->removeElement($astreinte)) {
            if ($astreinte->getEducateur() === $this) {
                $astreinte->setEducateur(null);
            }
        }

        return $this;
    }

    /**
     * Get current astreinte (if any)
     */
    public function getCurrentAstreinte(): ?Astreinte
    {
        $now = new \DateTime();

        foreach ($this->astreintes as $astreinte) {
            if ($astreinte->getStartAt() <= $now && $astreinte->getEndAt() >= $now) {
                return $astreinte;
            }
        }

        return null;
    }

    /**
     * Get upcoming astreintes
     * @return Astreinte[]
     */
    public function getUpcomingAstreintes(): array
    {
        $now = new \DateTime();
        $upcoming = [];

        foreach ($this->astreintes as $astreinte) {
            if ($astreinte->getStartAt() > $now) {
                $upcoming[] = $astreinte;
            }
        }

        // Sort by start date
        usort($upcoming, fn($a, $b) => $a->getStartAt() <=> $b->getStartAt());

        return $upcoming;
    }

    /**
     * Check if user is currently on-call
     */
    public function isOnCall(): bool
    {
        return $this->getCurrentAstreinte() !== null;
    }

    #[\Deprecated]
    public function eraseCredentials(): void
    {
        // @deprecated, to be removed when upgrading to Symfony 8
    }
}
