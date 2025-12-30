<?php

namespace App\Entity;

use App\Repository\SqueletteGardeRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SqueletteGardeRepository::class)]
#[ORM\HasLifecycleCallbacks]
class SqueletteGarde
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $nom = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::TEXT)]
    private string $configuration = '{"creneaux_garde":[],"creneaux_renfort":[],"options":{}}';

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $nombreUtilisations = 0;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $derniereUtilisation = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $createdBy = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $updatedBy = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
        $this->nombreUtilisations = 0;
        $this->configuration = '{"creneaux_garde":[],"creneaux_renfort":[],"options":{}}';
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTime();
    }

    // Helper methods for configuration management
    public function getConfigurationArray(): array
    {
        $decoded = json_decode($this->configuration, true);
        if (!is_array($decoded)) {
            return [
                'creneaux_garde' => [],
                'creneaux_renfort' => [],
                'options' => []
            ];
        }
        return $decoded;
    }

    public function setConfigurationArray(array $config): self
    {
        $this->configuration = json_encode($config);
        return $this;
    }

    public function getCreneauxGarde(): array
    {
        $config = $this->getConfigurationArray();
        return $config['creneaux_garde'] ?? [];
    }

    public function getCreneauxRenfort(): array
    {
        $config = $this->getConfigurationArray();
        return $config['creneaux_renfort'] ?? [];
    }

    public function incrementUtilisation(): void
    {
        $this->nombreUtilisations++;
        $this->derniereUtilisation = new \DateTime();
    }

    // Getters and setters
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): self
    {
        $this->nom = $nom;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getConfiguration(): string
    {
        return $this->configuration;
    }

    public function setConfiguration(string $configuration): self
    {
        $this->configuration = $configuration;
        return $this;
    }

    public function getNombreUtilisations(): int
    {
        return $this->nombreUtilisations;
    }

    public function setNombreUtilisations(int $nombreUtilisations): self
    {
        $this->nombreUtilisations = $nombreUtilisations;
        return $this;
    }

    public function getDerniereUtilisation(): ?\DateTimeInterface
    {
        return $this->derniereUtilisation;
    }

    public function setDerniereUtilisation(?\DateTimeInterface $derniereUtilisation): self
    {
        $this->derniereUtilisation = $derniereUtilisation;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeInterface $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $createdBy): self
    {
        $this->createdBy = $createdBy;
        return $this;
    }

    public function getUpdatedBy(): ?User
    {
        return $this->updatedBy;
    }

    public function setUpdatedBy(?User $updatedBy): self
    {
        $this->updatedBy = $updatedBy;
        return $this;
    }
}
