<?php

namespace App\Entity;

use App\Repository\MessageInterneRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MessageInterneRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(name: 'idx_message_expediteur', columns: ['expediteur_id'])]
#[ORM\Index(name: 'idx_message_date', columns: ['date_envoi'])]
class MessageInterne
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $expediteur;

    /**
     * @var Collection<int, User>
     */
    #[ORM\ManyToMany(targetEntity: User::class)]
    #[ORM\JoinTable(name: 'message_interne_destinataires')]
    private Collection $destinataires;

    // Optional: target by roles instead of users (WF78 - broadcast)
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $rolesCible = null;

    #[ORM\Column(length: 255)]
    private string $sujet;

    #[ORM\Column(type: Types::TEXT)]
    private string $contenu;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $dateEnvoi;

    // JSON array of Document IDs for attachments
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $piecesJointes = null;

    // Array of user IDs who have read the message
    #[ORM\Column(type: Types::JSON)]
    private array $luPar = [];

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $createdAt;

    public function __construct()
    {
        $this->destinataires = new ArrayCollection();
        $this->dateEnvoi = new \DateTime();
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getExpediteur(): User
    {
        return $this->expediteur;
    }

    public function setExpediteur(User $expediteur): static
    {
        $this->expediteur = $expediteur;
        return $this;
    }

    /**
     * @return Collection<int, User>
     */
    public function getDestinataires(): Collection
    {
        return $this->destinataires;
    }

    public function addDestinataire(User $user): static
    {
        if (!$this->destinataires->contains($user)) {
            $this->destinataires->add($user);
        }
        return $this;
    }

    public function removeDestinataire(User $user): static
    {
        $this->destinataires->removeElement($user);
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

    public function getSujet(): string
    {
        return $this->sujet;
    }

    public function setSujet(string $sujet): static
    {
        $this->sujet = $sujet;
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

    public function getDateEnvoi(): \DateTimeInterface
    {
        return $this->dateEnvoi;
    }

    public function setDateEnvoi(\DateTimeInterface $dateEnvoi): static
    {
        $this->dateEnvoi = $dateEnvoi;
        return $this;
    }

    public function getPiecesJointes(): ?array
    {
        return $this->piecesJointes;
    }

    public function setPiecesJointes(?array $piecesJointes): static
    {
        $this->piecesJointes = $piecesJointes;
        return $this;
    }

    public function getLuPar(): array
    {
        return $this->luPar;
    }

    public function setLuPar(array $luPar): static
    {
        $this->luPar = $luPar;
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
    public function markAsReadBy(User $user): static
    {
        $userId = $user->getId();
        if ($userId !== null && !in_array($userId, $this->luPar, true)) {
            $this->luPar[] = $userId;
        }
        return $this;
    }

    public function isReadBy(User $user): bool
    {
        return in_array($user->getId(), $this->luPar, true);
    }

    public function isUnreadBy(User $user): bool
    {
        return !$this->isReadBy($user);
    }

    public function getDestinatairesCount(): int
    {
        return $this->destinataires->count();
    }

    public function getReadCount(): int
    {
        return count($this->luPar);
    }

    public function isBroadcast(): bool
    {
        return !empty($this->rolesCible);
    }

    /**
     * Get a preview of the content (first X characters)
     */
    public function getContentPreview(int $length = 100): string
    {
        $stripped = strip_tags($this->contenu);
        if (mb_strlen($stripped) <= $length) {
            return $stripped;
        }
        return mb_substr($stripped, 0, $length) . '...';
    }
}
