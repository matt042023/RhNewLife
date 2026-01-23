<?php

namespace App\Entity;

use App\Repository\CompteurCPRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CompteurCPRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\UniqueConstraint(name: 'unique_user_periode', columns: ['user_id', 'periode_reference'])]
class CompteurCP
{
    /**
     * Acquisition mensuelle de CP (en jours)
     */
    public const MONTHLY_ACQUISITION = 2.5;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    /**
     * Période de référence au format "YYYY-YYYY" (ex: "2025-2026" = 1er juin 2025 au 31 mai 2026)
     */
    #[ORM\Column(length: 9)]
    private ?string $periodeReference = null;

    /**
     * Solde initial (report de la période précédente)
     */
    #[ORM\Column(type: Types::FLOAT)]
    private float $soldeInitial = 0.0;

    /**
     * Jours acquis depuis le début de la période
     */
    #[ORM\Column(type: Types::FLOAT)]
    private float $acquis = 0.0;

    /**
     * Jours pris depuis le début de la période
     */
    #[ORM\Column(type: Types::FLOAT)]
    private float $pris = 0.0;

    /**
     * Ajustement manuel par l'admin (+/-)
     */
    #[ORM\Column(type: Types::FLOAT)]
    private float $ajustementAdmin = 0.0;

    /**
     * Commentaire pour l'ajustement admin
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $ajustementComment = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updatedAt = null;

    public function __construct()
    {
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

    public function getPeriodeReference(): ?string
    {
        return $this->periodeReference;
    }

    public function setPeriodeReference(string $periodeReference): static
    {
        $this->periodeReference = $periodeReference;
        return $this;
    }

    /**
     * Retourne la date de début de la période (1er juin)
     */
    public function getPeriodeStart(): ?\DateTimeInterface
    {
        if (!$this->periodeReference) {
            return null;
        }

        $startYear = (int) substr($this->periodeReference, 0, 4);
        return new \DateTime("{$startYear}-06-01");
    }

    /**
     * Retourne la date de fin de la période (31 mai)
     */
    public function getPeriodeEnd(): ?\DateTimeInterface
    {
        if (!$this->periodeReference) {
            return null;
        }

        $endYear = (int) substr($this->periodeReference, 5, 4);
        return new \DateTime("{$endYear}-05-31");
    }

    /**
     * Retourne le libellé de la période (ex: "Juin 2025 - Mai 2026")
     */
    public function getPeriodeLabel(): string
    {
        if (!$this->periodeReference) {
            return '';
        }

        $startYear = substr($this->periodeReference, 0, 4);
        $endYear = substr($this->periodeReference, 5, 4);

        return "Juin {$startYear} - Mai {$endYear}";
    }

    public function getSoldeInitial(): float
    {
        return $this->soldeInitial;
    }

    public function setSoldeInitial(float $soldeInitial): static
    {
        $this->soldeInitial = $soldeInitial;
        return $this;
    }

    public function getAcquis(): float
    {
        return $this->acquis;
    }

    public function setAcquis(float $acquis): static
    {
        $this->acquis = $acquis;
        return $this;
    }

    /**
     * Ajoute des jours acquis
     */
    public function addAcquis(float $days): static
    {
        $this->acquis += $days;
        return $this;
    }

    public function getPris(): float
    {
        return $this->pris;
    }

    public function setPris(float $pris): static
    {
        $this->pris = $pris;
        return $this;
    }

    /**
     * Ajoute des jours pris
     */
    public function addPris(float $days): static
    {
        $this->pris += $days;
        return $this;
    }

    public function getAjustementAdmin(): float
    {
        return $this->ajustementAdmin;
    }

    public function setAjustementAdmin(float $ajustementAdmin): static
    {
        $this->ajustementAdmin = $ajustementAdmin;
        return $this;
    }

    public function getAjustementComment(): ?string
    {
        return $this->ajustementComment;
    }

    public function setAjustementComment(?string $ajustementComment): static
    {
        $this->ajustementComment = $ajustementComment;
        return $this;
    }

    /**
     * Retourne le solde actuel (soldeInitial + acquis - pris + ajustementAdmin)
     */
    public function getSoldeActuel(): float
    {
        return $this->soldeInitial + $this->acquis - $this->pris + $this->ajustementAdmin;
    }

    /**
     * Retourne le solde actuel formaté
     */
    public function getSoldeActuelFormatted(): string
    {
        return number_format($this->getSoldeActuel(), 2, ',', ' ');
    }

    /**
     * Vérifie si le solde est suffisant pour prendre X jours
     */
    public function hasSufficientBalance(float $days): bool
    {
        return $this->getSoldeActuel() >= $days;
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
     * Calcule la période de référence actuelle (basée sur la date courante)
     */
    public static function getCurrentPeriodeReference(?\DateTimeInterface $date = null): string
    {
        $date ??= new \DateTime();
        $year = (int) $date->format('Y');
        $month = (int) $date->format('n');

        // Si on est entre janvier et mai, la période a commencé l'année précédente
        if ($month < 6) {
            return ($year - 1) . '-' . $year;
        }

        // Si on est entre juin et décembre, la période se termine l'année suivante
        return $year . '-' . ($year + 1);
    }

    /**
     * Calcule le prorata pour un mois donné basé sur la date d'embauche
     *
     * @param int $year Année
     * @param int $month Mois (1-12)
     * @param \DateTimeInterface|null $hiringDate Date d'embauche
     * @return float Prorata (entre 0 et 1)
     */
    public static function calculateProrata(int $year, int $month, ?\DateTimeInterface $hiringDate): float
    {
        if (!$hiringDate) {
            return 1.0;
        }

        // Premier jour du mois
        $monthStart = new \DateTime("{$year}-{$month}-01");
        // Dernier jour du mois
        $monthEnd = (clone $monthStart)->modify('last day of this month');
        // Nombre de jours dans le mois
        $daysInMonth = (int) $monthEnd->format('j');

        // Si embauche avant le mois, prorata = 1
        if ($hiringDate < $monthStart) {
            return 1.0;
        }

        // Si embauche après le mois, prorata = 0
        if ($hiringDate > $monthEnd) {
            return 0.0;
        }

        // Nombre de jours depuis l'embauche jusqu'à la fin du mois
        $hiringDay = (int) $hiringDate->format('j');
        $daysWorked = $daysInMonth - $hiringDay + 1;

        return $daysWorked / $daysInMonth;
    }

    /**
     * Calcule les CP acquis pour un mois donné
     *
     * @param int $year Année
     * @param int $month Mois (1-12)
     * @param \DateTimeInterface|null $hiringDate Date d'embauche
     * @return float Jours de CP acquis
     */
    public static function calculateMonthlyAcquisition(int $year, int $month, ?\DateTimeInterface $hiringDate): float
    {
        $prorata = self::calculateProrata($year, $month, $hiringDate);
        return round(self::MONTHLY_ACQUISITION * $prorata, 2);
    }
}
