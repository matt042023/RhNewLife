<?php

namespace App\Entity;

use App\Repository\AnnonceInterneRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AnnonceInterneRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(name: 'idx_annonce_date', columns: ['date_publication'])]
#[ORM\Index(name: 'idx_annonce_epingle', columns: ['epingle'])]
#[ORM\Index(name: 'idx_annonce_actif', columns: ['actif'])]
class AnnonceInterne
{
    // Visibility constants
    public const VISIBILITY_TOUS = 'tous';
    public const VISIBILITY_ADMIN = 'admin';
    public const VISIBILITY_EDU = 'educateur';
    public const VISIBILITY_DIR = 'direction';

    public const VISIBILITIES = [
        self::VISIBILITY_TOUS => 'Tous les utilisateurs',
        self::VISIBILITY_ADMIN => 'Administrateurs uniquement',
        self::VISIBILITY_EDU => 'Educateurs',
        self::VISIBILITY_DIR => 'Direction',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $titre;

    #[ORM\Column(type: Types::TEXT)]
    private string $contenu;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $datePublication;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $publiePar;

    #[ORM\Column(length: 20, options: ['default' => self::VISIBILITY_TOUS])]
    private string $visibilite = self::VISIBILITY_TOUS;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $image = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    private bool $epingle = false;

    // R3: 30 days expiration (configurable via parameter)
    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dateExpiration = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    private bool $actif = true;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $updatedAt;

    public function __construct()
    {
        $now = new \DateTime();
        $this->datePublication = $now;
        $this->createdAt = $now;
        $this->updatedAt = $now;
        // Default expiration: 30 days from publication
        $this->dateExpiration = (clone $now)->modify('+30 days');
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

    public function getTitre(): string
    {
        return $this->titre;
    }

    public function setTitre(string $titre): static
    {
        $this->titre = $titre;
        return $this;
    }

    public function getContenu(): string
    {
        return $this->contenu;
    }

    public function setContenu(string $contenu): static
    {
        $this->contenu = $contenu;
        return $this;
    }

    public function getDatePublication(): \DateTimeInterface
    {
        return $this->datePublication;
    }

    public function setDatePublication(\DateTimeInterface $datePublication): static
    {
        $this->datePublication = $datePublication;
        return $this;
    }

    public function getPubliePar(): User
    {
        return $this->publiePar;
    }

    public function setPubliePar(User $publiePar): static
    {
        $this->publiePar = $publiePar;
        return $this;
    }

    public function getVisibilite(): string
    {
        return $this->visibilite;
    }

    public function setVisibilite(string $visibilite): static
    {
        $this->visibilite = $visibilite;
        return $this;
    }

    public function getVisibiliteLabel(): string
    {
        return self::VISIBILITIES[$this->visibilite] ?? $this->visibilite;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(?string $image): static
    {
        $this->image = $image;
        return $this;
    }

    public function isEpingle(): bool
    {
        return $this->epingle;
    }

    public function setEpingle(bool $epingle): static
    {
        $this->epingle = $epingle;
        return $this;
    }

    public function getDateExpiration(): ?\DateTimeInterface
    {
        return $this->dateExpiration;
    }

    public function setDateExpiration(?\DateTimeInterface $dateExpiration): static
    {
        $this->dateExpiration = $dateExpiration;
        return $this;
    }

    public function isActif(): bool
    {
        return $this->actif;
    }

    public function setActif(bool $actif): static
    {
        $this->actif = $actif;
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

    public function getUpdatedAt(): \DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    // Helper methods
    public function isExpired(): bool
    {
        if (!$this->dateExpiration) {
            return false;
        }
        return $this->dateExpiration < new \DateTime();
    }

    public function isVisible(): bool
    {
        return $this->actif && !$this->isExpired();
    }

    public function isVisibleToUser(User $user): bool
    {
        if (!$this->isVisible()) {
            return false;
        }

        if ($this->visibilite === self::VISIBILITY_TOUS) {
            return true;
        }

        $roles = $user->getRoles();

        return match ($this->visibilite) {
            self::VISIBILITY_ADMIN => in_array('ROLE_ADMIN', $roles, true),
            self::VISIBILITY_DIR => in_array('ROLE_DIRECTOR', $roles, true) || in_array('ROLE_ADMIN', $roles, true),
            self::VISIBILITY_EDU => true, // All authenticated users can see educator announcements
            default => true,
        };
    }

    /**
     * Get a preview of the content (first X characters)
     */
    public function getContentPreview(int $length = 150): string
    {
        $stripped = strip_tags($this->contenu);
        if (mb_strlen($stripped) <= $length) {
            return $stripped;
        }
        return mb_substr($stripped, 0, $length) . '...';
    }

    /**
     * Get days until expiration (or negative if expired)
     */
    public function getDaysUntilExpiration(): ?int
    {
        if (!$this->dateExpiration) {
            return null;
        }

        $now = new \DateTime();
        $interval = $now->diff($this->dateExpiration);

        return $interval->invert ? -$interval->days : $interval->days;
    }

    public function toggleEpingle(): static
    {
        $this->epingle = !$this->epingle;
        return $this;
    }

    public function deactivate(): static
    {
        $this->actif = false;
        return $this;
    }

    public function activate(): static
    {
        $this->actif = true;
        return $this;
    }
}
