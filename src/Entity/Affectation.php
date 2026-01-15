<?php

namespace App\Entity;

use App\Repository\AffectationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AffectationRepository::class)]
class Affectation
{
    public const TYPE_GARDE_48H = 'garde_48h';
    public const TYPE_GARDE_24H = 'garde_24h';
    public const TYPE_RENFORT = 'renfort';
    public const TYPE_AUTRE = 'autre';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_VALIDATED = 'validated';
    public const STATUS_TO_REPLACE_ABSENCE = 'to_replace_absence';
    public const STATUS_TO_REPLACE_RDV = 'to_replace_rdv';
    public const STATUS_TO_REPLACE_SCHEDULE_CONFLICT = 'to_replace_schedule_conflict';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'affectations')]
    #[ORM\JoinColumn(nullable: false)]
    private ?PlanningMonth $planningMois = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $user = null;

    #[ORM\ManyToOne(inversedBy: 'affectations')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Villa $villa = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $startAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $endAt = null;

    #[ORM\Column(length: 20)]
    private ?string $type = null;

    #[ORM\Column(length: 30)]
    private ?string $statut = self::STATUS_DRAFT;

    #[ORM\Column]
    private ?bool $isFromSquelette = false;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $commentaire = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $joursTravailes = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    private bool $isSegmented = false;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $segmentNumber = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $totalSegments = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPlanningMois(): ?PlanningMonth
    {
        return $this->planningMois;
    }

    public function setPlanningMois(?PlanningMonth $planningMois): static
    {
        $this->planningMois = $planningMois;

        return $this;
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

    public function getVilla(): ?Villa
    {
        return $this->villa;
    }

    public function setVilla(?Villa $villa): static
    {
        $this->villa = $villa;

        return $this;
    }

    public function getStartAt(): ?\DateTimeInterface
    {
        return $this->startAt;
    }

    public function setStartAt(\DateTimeInterface $startAt): static
    {
        $this->startAt = $startAt;

        return $this;
    }

    public function getEndAt(): ?\DateTimeInterface
    {
        return $this->endAt;
    }

    public function setEndAt(\DateTimeInterface $endAt): static
    {
        $this->endAt = $endAt;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): static
    {
        $this->statut = $statut;

        return $this;
    }

    public function isIsFromSquelette(): ?bool
    {
        return $this->isFromSquelette;
    }

    public function setIsFromSquelette(bool $isFromSquelette): static
    {
        $this->isFromSquelette = $isFromSquelette;

        return $this;
    }

    public function getCommentaire(): ?string
    {
        return $this->commentaire;
    }

    public function setCommentaire(?string $commentaire): static
    {
        $this->commentaire = $commentaire;

        return $this;
    }

    public function getJoursTravailes(): ?int
    {
        return $this->joursTravailes;
    }

    public function setJoursTravailes(?int $joursTravailes): static
    {
        $this->joursTravailes = $joursTravailes;

        return $this;
    }

    public function getIsSegmented(): bool
    {
        return $this->isSegmented;
    }

    public function setIsSegmented(bool $isSegmented): static
    {
        $this->isSegmented = $isSegmented;

        return $this;
    }

    public function getSegmentNumber(): ?int
    {
        return $this->segmentNumber;
    }

    public function setSegmentNumber(?int $segmentNumber): static
    {
        $this->segmentNumber = $segmentNumber;

        return $this;
    }

    public function getTotalSegments(): ?int
    {
        return $this->totalSegments;
    }

    public function setTotalSegments(?int $totalSegments): static
    {
        $this->totalSegments = $totalSegments;

        return $this;
    }
}
