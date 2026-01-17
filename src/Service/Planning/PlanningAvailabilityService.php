<?php

namespace App\Service\Planning;

use App\Entity\Absence;
use App\Entity\Affectation;
use App\Entity\RendezVous;
use App\Entity\User;
use App\Repository\AbsenceRepository;
use App\Repository\AffectationRepository;
use App\Repository\RendezVousRepository;

class PlanningAvailabilityService
{
    public function __construct(
        private AbsenceRepository $absenceRepository,
        private RendezVousRepository $rendezVousRepository,
        private AffectationRepository $affectationRepository
    ) {
    }

    /**
     * Get user availability for a specific period
     * Returns absences, RDVs and existing affectations
     */
    public function getUserAvailabilityForPeriod(User $user, \DateTime $start, \DateTime $end): array
    {
        // Récupérer absences approuvées
        $absences = $this->absenceRepository->createQueryBuilder('a')
            ->where('a.user = :user')
            ->andWhere('a.status = :approved')
            ->andWhere('a.startAt <= :end')
            ->andWhere('a.endAt >= :start')
            ->setParameter('user', $user)
            ->setParameter('approved', Absence::STATUS_APPROVED)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getResult();

        // Récupérer RDV confirmés avec impact garde
        $rdvs = $this->rendezVousRepository->createQueryBuilder('r')
            ->join('r.participants', 'p')
            ->where('p.id = :userId')
            ->andWhere('r.impactGarde = true')
            ->andWhere('r.startAt <= :end')
            ->andWhere('r.endAt >= :start')
            ->andWhere('r.statut != :cancelled')
            ->setParameter('userId', $user->getId())
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->setParameter('cancelled', RendezVous::STATUS_CANCELLED)
            ->getQuery()
            ->getResult();

        // Récupérer affectations existantes
        $affectations = $this->affectationRepository->createQueryBuilder('a')
            ->where('a.user = :user')
            ->andWhere('a.startAt <= :end')
            ->andWhere('a.endAt >= :start')
            ->setParameter('user', $user)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getResult();

        return [
            'absences' => array_map(fn($a) => [
                'start' => $a->getStartAt(),
                'end' => $a->getEndAt(),
                'type' => $a->getAbsenceType()?->getCode() ?? 'unknown',
                'color' => $this->getAbsenceColor($a->getAbsenceType()),
                'label' => $a->getAbsenceType()?->getLabel() ?? 'Absence'
            ], $absences),
            'rdvs' => array_map(fn($r) => [
                'start' => $r->getStartAt(),
                'end' => $r->getEndAt(),
                'type' => 'rdv',
                'color' => '#FDE047',
                'label' => $r->getTitle() ?? 'RDV'
            ], $rdvs),
            'affectations_existantes' => array_map(fn($a) => [
                'start' => $a->getStartAt(),
                'end' => $a->getEndAt(),
                'villa' => $a->getVilla()?->getNom(),
                'type' => $a->getType()
            ], $affectations)
        ];
    }

    /**
     * Get availability status for all users on a specific date
     */
    public function getUsersAvailabilityStatus(\DateTime $date): array
    {
        // TODO: Implement when needed
        return [];
    }

    /**
     * Get color based on absence type
     */
    private function getAbsenceColor($type): string
    {
        if (!$type) {
            return '#FCA5A5'; // Rouge clair par défaut
        }

        $code = $type->getCode();

        return match($code) {
            'CP', 'RTT' => '#FCA5A5',  // Rouge clair
            'MAL', 'AT' => '#FDBA74',   // Orange
            default => '#FCA5A5'
        };
    }

    /**
     * Check if user has overlapping absences or RDVs for an affectation
     */
    public function checkAbsenceOverlaps(Affectation $affectation): array
    {
        $user = $affectation->getUser();
        if (!$user) {
            return [];
        }

        $availability = $this->getUserAvailabilityForPeriod(
            $user,
            $affectation->getStartAt(),
            $affectation->getEndAt()
        );

        $overlaps = [];

        foreach ($availability['absences'] as $absence) {
            $overlaps[] = [
                'type' => 'absence_overlap',
                'subtype' => $absence['type'],
                'start' => $absence['start'],
                'end' => $absence['end'],
                'label' => $absence['label'],
                'severity' => 'warning'
            ];
        }

        foreach ($availability['rdvs'] as $rdv) {
            $overlaps[] = [
                'type' => 'rdv_overlap',
                'subtype' => 'rdv',
                'start' => $rdv['start'],
                'end' => $rdv['end'],
                'label' => $rdv['label'],
                'severity' => 'warning'
            ];
        }

        return $overlaps;
    }
}
