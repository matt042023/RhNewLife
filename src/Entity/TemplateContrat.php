<?php

namespace App\Entity;

use App\Repository\TemplateContratRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: TemplateContratRepository::class)]
#[ORM\HasLifecycleCallbacks]
class TemplateContrat
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le nom du template est obligatoire.')]
    #[Assert\Length(
        min: 3,
        max: 255,
        minMessage: 'Le nom doit contenir au moins {{ limit }} caractères.',
        maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères.'
    )]
    private ?string $name = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'Le contenu HTML est obligatoire.')]
    private ?string $contentHtml = null;

    #[ORM\Column(options: ['default' => true])]
    private bool $active = true;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $createdBy = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $modifiedBy = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updatedAt = null;

    /**
     * @var Collection<int, Contract>
     */
    #[ORM\OneToMany(targetEntity: Contract::class, mappedBy: 'template')]
    private Collection $contracts;

    public function __construct()
    {
        $this->contracts = new ArrayCollection();
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
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

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
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

    public function getContentHtml(): ?string
    {
        return $this->contentHtml;
    }

    public function setContentHtml(string $contentHtml): static
    {
        $this->contentHtml = $contentHtml;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): static
    {
        $this->active = $active;
        return $this;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $createdBy): static
    {
        $this->createdBy = $createdBy;
        return $this;
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
            $contract->setTemplate($this);
        }

        return $this;
    }

    public function removeContract(Contract $contract): static
    {
        if ($this->contracts->removeElement($contract)) {
            if ($contract->getTemplate() === $this) {
                $contract->setTemplate(null);
            }
        }

        return $this;
    }

    /**
     * Retourne la liste des variables disponibles pour les templates
     */
    public static function getAvailableVariables(): array
    {
        return [
            'Employé' => [
                '{{ employee.firstName }}' => 'Prénom',
                '{{ employee.lastName }}' => 'Nom de famille',
                '{{ employee.fullName }}' => 'Nom complet',
                '{{ employee.email }}' => 'Email',
                '{{ employee.phone }}' => 'Téléphone',
                '{{ employee.address }}' => 'Adresse complète',
                '{{ employee.matricule }}' => 'Matricule',
                '{{ employee.position }}' => 'Poste',
                '{{ employee.villa }}' => 'Villa',
                '{{ employee.familyStatus }}' => 'Situation familiale',
                '{{ employee.children }}' => 'Nombre d\'enfants',
                '{{ employee.iban }}' => 'IBAN',
                '{{ employee.bic }}' => 'BIC',
                '{{ employee.hiringDate }}' => 'Date d\'embauche',
            ],
            'Contrat' => [
                '{{ contract.type }}' => 'Type de contrat (CDI, CDD, etc.)',
                '{{ contract.startDate }}' => 'Date de début',
                '{{ contract.endDate }}' => 'Date de fin',
                '{{ contract.baseSalary }}' => 'Salaire de base',
                '{{ contract.weeklyHours }}' => 'Heures hebdomadaires (ancien système)',
                '{{ contract.activityRate }}' => 'Taux d\'activité (ancien système)',
                '{{ contract.workingDaysFormatted }}' => 'Jours travaillés formatés (ancien système)',
                '{{ contract.useAnnualDaySystem }}' => 'Utilise le système de jours annuels (true/false)',
                '{{ contract.annualDaysRequired }}' => 'Nombre de jours annuels à effectuer (258)',
                '{{ contract.annualDayNotes }}' => 'Notes sur le compteur annuel',
                '{{ contract.villa }}' => 'Villa affectée',
                '{{ contract.essaiEndDate }}' => 'Fin période d\'essai',
                '{{ contract.signedAt }}' => 'Date/heure de signature',
                '{{ contract.signatureIp }}' => 'Adresse IP de signature',
            ],
            'Dates' => [
                '{{ currentDate }}' => 'Date de génération du contrat',
                '{{ currentYear }}' => 'Année en cours',
            ],
        ];
    }

    /**
     * Retourne la liste des variables sous forme plate pour validation
     */
    public static function getValidVariablesList(): array
    {
        $variables = [];
        foreach (self::getAvailableVariables() as $category => $vars) {
            $variables = array_merge($variables, array_keys($vars));
        }
        return $variables;
    }

    /**
     * Extrait les variables utilisées dans le contenu HTML
     */
    public function extractUsedVariables(): array
    {
        if (!$this->contentHtml) {
            return [];
        }

        preg_match_all('/\{\{\s*([a-zA-Z0-9_.]+)\s*\}\}/', $this->contentHtml, $matches);
        return array_unique($matches[1]);
    }

    /**
     * Vérifie si toutes les variables utilisées sont valides
     */
    public function hasValidVariables(): bool
    {
        $usedVars = $this->extractUsedVariables();
        $validVars = self::getValidVariablesList();

        foreach ($usedVars as $var) {
            $fullVar = '{{ ' . $var . ' }}';
            if (!in_array($fullVar, $validVars)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Retourne les variables invalides
     */
    public function getInvalidVariables(): array
    {
        $usedVars = $this->extractUsedVariables();
        $validVars = self::getValidVariablesList();
        $invalid = [];

        foreach ($usedVars as $var) {
            $fullVar = '{{ ' . $var . ' }}';
            if (!in_array($fullVar, $validVars)) {
                $invalid[] = $fullVar;
            }
        }

        return $invalid;
    }
}
