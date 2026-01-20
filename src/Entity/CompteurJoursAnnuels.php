<?php

namespace App\Entity;

use App\Repository\CompteurJoursAnnuelsRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CompteurJoursAnnuelsRepository::class)]
#[ORM\Table(name: 'compteur_jours_annuels')]
#[ORM\UniqueConstraint(name: 'unique_user_year', columns: ['user_id', 'year'])]
#[ORM\HasLifecycleCallbacks]
class CompteurJoursAnnuels
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull(message: 'L\'utilisateur est obligatoire.')]
    private ?User $user = null;

    #[ORM\Column(type: Types::INTEGER)]
    #[Assert\NotNull(message: 'L\'année est obligatoire.')]
    #[Assert\Range(
        min: 2000,
        max: 2100,
        notInRangeMessage: 'L\'année doit être entre {{ min }} et {{ max }}.'
    )]
    private ?int $year = null;

    /**
     * Total de jours alloués pour l'année (258 ou proratisé selon date d'embauche)
     */
    #[ORM\Column(type: Types::FLOAT)]
    #[Assert\NotBlank(message: 'Le nombre de jours alloués est obligatoire.')]
    #[Assert\Positive(message: 'Le nombre de jours alloués doit être positif.')]
    private float $joursAlloues = 258.0;

    /**
     * Jours consommés par les affectations/shifts (garde, renfort)
     */
    #[ORM\Column(type: Types::FLOAT)]
    #[Assert\PositiveOrZero(message: 'Le nombre de jours consommés doit être positif ou zéro.')]
    private float $joursConsommes = 0.0;

    /**
     * Date d'embauche (pour référence lors du calcul prorata)
     */
    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dateEmbauche = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTime();
    }

    // Getters and Setters

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

    public function getYear(): ?int
    {
        return $this->year;
    }

    public function setYear(int $year): static
    {
        $this->year = $year;
        return $this;
    }

    public function getJoursAlloues(): float
    {
        return $this->joursAlloues;
    }

    public function setJoursAlloues(float $joursAlloues): static
    {
        $this->joursAlloues = $joursAlloues;
        return $this;
    }

    public function getJoursConsommes(): float
    {
        return $this->joursConsommes;
    }

    public function setJoursConsommes(float $joursConsommes): static
    {
        $this->joursConsommes = $joursConsommes;
        return $this;
    }

    public function getDateEmbauche(): ?\DateTimeInterface
    {
        return $this->dateEmbauche;
    }

    public function setDateEmbauche(?\DateTimeInterface $dateEmbauche): static
    {
        $this->dateEmbauche = $dateEmbauche;
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

    // Helper Methods

    /**
     * Calcule les jours restants (alloués - consommés)
     */
    public function getJoursRestants(): float
    {
        return $this->joursAlloues - $this->joursConsommes;
    }

    /**
     * Vérifie si le compteur a un solde suffisant
     */
    public function hasSufficientBalance(float $jours): bool
    {
        return $this->getJoursRestants() >= $jours;
    }

    /**
     * Vérifie si le solde est négatif
     */
    public function isNegative(): bool
    {
        return $this->getJoursRestants() < 0;
    }

    /**
     * Retourne le pourcentage de jours consommés
     */
    public function getPercentageUsed(): float
    {
        if ($this->joursAlloues == 0) {
            return 0.0;
        }

        return round(($this->joursConsommes / $this->joursAlloues) * 100, 2);
    }

    /**
     * Retourne le pourcentage de jours restants
     */
    public function getPercentageRemaining(): float
    {
        return 100 - $this->getPercentageUsed();
    }

    /**
     * Formate les jours restants pour affichage
     */
    public function getJoursRestantsFormatted(): string
    {
        $remaining = $this->getJoursRestants();
        return number_format($remaining, 0) . ' jours';
    }

    /**
     * Formate les jours alloués pour affichage
     */
    public function getJoursAllouesFormatted(): string
    {
        return number_format($this->joursAlloues, 0) . ' jours';
    }
}
