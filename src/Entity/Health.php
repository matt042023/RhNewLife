<?php

namespace App\Entity;

use App\Repository\HealthRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: HealthRepository::class)]
class Health
{
  #[ORM\Id]
  #[ORM\GeneratedValue]
  #[ORM\Column]
  private ?int $id = null;

  #[ORM\OneToOne(inversedBy: 'health', cascade: ['persist', 'remove'])]
  #[ORM\JoinColumn(nullable: false)]
  private ?User $user = null;

  #[ORM\Column(options: ['default' => false])]
  private bool $mutuelleEnabled = false;

  #[ORM\Column(length: 255, nullable: true)]
  private ?string $mutuelleNom = null;

  #[ORM\Column(length: 255, nullable: true)]
  private ?string $mutuelleFormule = null;

  #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
  private ?\DateTimeInterface $mutuelleDateFin = null;

  #[ORM\Column(options: ['default' => false])]
  private bool $prevoyanceEnabled = false;

  #[ORM\Column(length: 255, nullable: true)]
  private ?string $prevoyanceNom = null;

  public function getId(): ?int
  {
    return $this->id;
  }

  public function getUser(): ?User
  {
    return $this->user;
  }

  public function setUser(User $user): static
  {
    $this->user = $user;

    return $this;
  }

  public function isMutuelleEnabled(): bool
  {
    return $this->mutuelleEnabled;
  }

  public function setMutuelleEnabled(bool $mutuelleEnabled): static
  {
    $this->mutuelleEnabled = $mutuelleEnabled;

    return $this;
  }

  public function getMutuelleNom(): ?string
  {
    return $this->mutuelleNom;
  }

  public function setMutuelleNom(?string $mutuelleNom): static
  {
    $this->mutuelleNom = $mutuelleNom;

    return $this;
  }

  public function getMutuelleFormule(): ?string
  {
    return $this->mutuelleFormule;
  }

  public function setMutuelleFormule(?string $mutuelleFormule): static
  {
    $this->mutuelleFormule = $mutuelleFormule;

    return $this;
  }

  public function getMutuelleDateFin(): ?\DateTimeInterface
  {
    return $this->mutuelleDateFin;
  }

  public function setMutuelleDateFin(?\DateTimeInterface $mutuelleDateFin): static
  {
    $this->mutuelleDateFin = $mutuelleDateFin;

    return $this;
  }

  public function isPrevoyanceEnabled(): bool
  {
    return $this->prevoyanceEnabled;
  }

  public function setPrevoyanceEnabled(bool $prevoyanceEnabled): static
  {
    $this->prevoyanceEnabled = $prevoyanceEnabled;

    return $this;
  }

  public function getPrevoyanceNom(): ?string
  {
    return $this->prevoyanceNom;
  }

  public function setPrevoyanceNom(?string $prevoyanceNom): static
  {
    $this->prevoyanceNom = $prevoyanceNom;

    return $this;
  }
}
