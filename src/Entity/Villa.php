<?php

namespace App\Entity;

use App\Repository\VillaRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: VillaRepository::class)]
class Villa
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $nom = null;

    #[ORM\Column(length: 7, nullable: true)]
    private ?string $color = null;

    #[ORM\OneToMany(mappedBy: 'villa', targetEntity: Affectation::class)]
    private Collection $affectations;

    /**
     * @var Collection<int, User>
     */
    #[ORM\OneToMany(mappedBy: 'villa', targetEntity: User::class)]
    private Collection $users;

    /**
     * @var Collection<int, Contract>
     */
    #[ORM\OneToMany(mappedBy: 'villa', targetEntity: Contract::class)]
    private Collection $contracts;

    public function __construct()
    {
        $this->affectations = new ArrayCollection();
        $this->users = new ArrayCollection();
        $this->contracts = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;

        return $this;
    }

    public function getColor(): ?string
    {
        return $this->color;
    }

    public function setColor(?string $color): static
    {
        $this->color = $color;

        return $this;
    }

    /**
     * @return Collection<int, Affectation>
     */
    public function getAffectations(): Collection
    {
        return $this->affectations;
    }

    public function addAffectation(Affectation $affectation): static
    {
        if (!$this->affectations->contains($affectation)) {
            $this->affectations->add($affectation);
            $affectation->setVilla($this);
        }

        return $this;
    }

    public function removeAffectation(Affectation $affectation): static
    {
        if ($this->affectations->removeElement($affectation)) {
            // set the owning side to null (unless already changed)
            if ($affectation->getVilla() === $this) {
                $affectation->setVilla(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, User>
     */
    public function getUsers(): Collection
    {
        return $this->users;
    }

    public function addUser(User $user): static
    {
        if (!$this->users->contains($user)) {
            $this->users->add($user);
            $user->setVilla($this);
        }

        return $this;
    }

    public function removeUser(User $user): static
    {
        if ($this->users->removeElement($user)) {
            if ($user->getVilla() === $this) {
                $user->setVilla(null);
            }
        }

        return $this;
    }

    /**
     * Compte les utilisateurs actifs assignés à cette villa
     */
    public function getActiveUsersCount(): int
    {
        return $this->users->filter(function(User $user) {
            return $user->getStatus() === User::STATUS_ACTIVE;
        })->count();
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
            $contract->setVilla($this);
        }

        return $this;
    }

    public function removeContract(Contract $contract): static
    {
        if ($this->contracts->removeElement($contract)) {
            if ($contract->getVilla() === $this) {
                $contract->setVilla(null);
            }
        }

        return $this;
    }

    /**
     * Vérifie si la villa peut être supprimée en toute sécurité
     */
    public function canBeDeleted(): bool
    {
        return $this->users->isEmpty() && $this->affectations->isEmpty() && $this->contracts->isEmpty();
    }
}
