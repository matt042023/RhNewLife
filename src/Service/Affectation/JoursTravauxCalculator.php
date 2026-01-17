<?php

namespace App\Service\Affectation;

use App\Entity\Affectation;
use App\Entity\User;
use App\Repository\AffectationRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service to calculate working days from affectations with distinction between:
 * - Main shifts (garde_24h, garde_48h): Regular shifts linked to specific villas
 * - Reinforcements (renfort): Additional shifts covering the entire center or specific villas
 *
 * Used for payroll processing and monthly reports.
 */
class JoursTravauxCalculator
{
    public function __construct(
        private AffectationRepository $affectationRepository,
        private EntityManagerInterface $em
    ) {}

    /**
     * Calculate working days for a specific user and month
     * Returns detailed breakdown by shift type
     *
     * @param User $user The educator
     * @param \DateTimeInterface $startDate Start of period (inclusive)
     * @param \DateTimeInterface $endDate End of period (inclusive)
     * @return array{
     *   gardes_principales: array{total_jours: float, total_heures: int, affectations: array},
     *   renforts: array{total_jours: float, total_heures: int, affectations: array},
     *   total: array{jours: float, heures: int}
     * }
     */
    public function calculateForUser(User $user, \DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        // Fetch all affectations for user in period
        $affectations = $this->affectationRepository->createQueryBuilder('a')
            ->where('a.user = :user')
            ->andWhere('a.startAt >= :startDate')
            ->andWhere('a.startAt <= :endDate')
            ->andWhere('a.statut IN (:validStatuses)')
            ->setParameter('user', $user)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->setParameter('validStatuses', ['validated', 'to_replace_absence', 'to_replace_rdv'])
            ->orderBy('a.startAt', 'ASC')
            ->getQuery()
            ->getResult();

        $gardesPrincipales = [
            'total_jours' => 0.0,
            'total_heures' => 0,
            'affectations' => []
        ];

        $renforts = [
            'total_jours' => 0.0,
            'total_heures' => 0,
            'affectations' => []
        ];

        foreach ($affectations as $affectation) {
            $joursCalcules = $affectation->getJoursCalcules() ?? 0.0;
            $heures = $this->calculateHours($affectation);

            $affectationData = [
                'id' => $affectation->getId(),
                'type' => $affectation->getType(),
                'start' => $affectation->getStartAt(),
                'end' => $affectation->getEndAt(),
                'villa' => $affectation->getVilla()?->getNom(),
                'jours' => $joursCalcules,
                'heures' => $heures,
                'statut' => $affectation->getStatut()
            ];

            if ($this->isMainShift($affectation)) {
                $gardesPrincipales['total_jours'] += $joursCalcules;
                $gardesPrincipales['total_heures'] += $heures;
                $gardesPrincipales['affectations'][] = $affectationData;
            } else {
                $renforts['total_jours'] += $joursCalcules;
                $renforts['total_heures'] += $heures;
                $renforts['affectations'][] = $affectationData;
            }
        }

        return [
            'gardes_principales' => $gardesPrincipales,
            'renforts' => $renforts,
            'total' => [
                'jours' => $gardesPrincipales['total_jours'] + $renforts['total_jours'],
                'heures' => $gardesPrincipales['total_heures'] + $renforts['total_heures']
            ]
        ];
    }

    /**
     * Calculate working days for all users in a given period
     * Returns array indexed by user ID
     *
     * @param \DateTimeInterface $startDate
     * @param \DateTimeInterface $endDate
     * @return array Array of calculations indexed by user ID
     */
    public function calculateForAllUsers(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        // Get all users who have affectations in the period
        $usersWithAffectations = $this->affectationRepository->createQueryBuilder('a')
            ->select('DISTINCT IDENTITY(a.user) as userId')
            ->where('a.startAt >= :startDate')
            ->andWhere('a.startAt <= :endDate')
            ->andWhere('a.user IS NOT NULL')
            ->andWhere('a.statut IN (:validStatuses)')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->setParameter('validStatuses', ['validated', 'to_replace_absence', 'to_replace_rdv'])
            ->getQuery()
            ->getResult();

        $results = [];
        foreach ($usersWithAffectations as $row) {
            $user = $this->em->getReference(User::class, $row['userId']);
            $results[$row['userId']] = [
                'user' => $user,
                'calculation' => $this->calculateForUser($user, $startDate, $endDate)
            ];
        }

        return $results;
    }

    /**
     * Check if affectation is a main shift (garde principale)
     */
    private function isMainShift(Affectation $affectation): bool
    {
        return in_array($affectation->getType(), [
            Affectation::TYPE_GARDE_24H,
            Affectation::TYPE_GARDE_48H
        ]);
    }

    /**
     * Calculate total hours for an affectation
     */
    private function calculateHours(Affectation $affectation): int
    {
        $start = $affectation->getStartAt();
        $end = $affectation->getEndAt();

        if (!$start || !$end) {
            return 0;
        }

        $diff = $end->getTimestamp() - $start->getTimestamp();
        return (int) round($diff / 3600);
    }

    /**
     * Generate a monthly report summary for export
     * Formats data for PDF/Excel generation
     *
     * @param int $year
     * @param int $month
     * @return array
     */
    public function generateMonthlyReport(int $year, int $month): array
    {
        $startDate = new \DateTime("$year-$month-01 00:00:00");
        $endDate = (clone $startDate)->modify('last day of this month')->setTime(23, 59, 59);

        $calculations = $this->calculateForAllUsers($startDate, $endDate);

        $report = [
            'period' => [
                'year' => $year,
                'month' => $month,
                'start' => $startDate,
                'end' => $endDate
            ],
            'users' => [],
            'totals' => [
                'gardes_principales_jours' => 0.0,
                'gardes_principales_heures' => 0,
                'renforts_jours' => 0.0,
                'renforts_heures' => 0,
                'total_jours' => 0.0,
                'total_heures' => 0
            ]
        ];

        foreach ($calculations as $userId => $data) {
            $user = $data['user'];
            $calc = $data['calculation'];

            $report['users'][] = [
                'id' => $user->getId(),
                'fullName' => $user->getFullName(),
                'email' => $user->getEmail(),
                'gardes_principales' => $calc['gardes_principales'],
                'renforts' => $calc['renforts'],
                'total' => $calc['total']
            ];

            // Accumulate totals
            $report['totals']['gardes_principales_jours'] += $calc['gardes_principales']['total_jours'];
            $report['totals']['gardes_principales_heures'] += $calc['gardes_principales']['total_heures'];
            $report['totals']['renforts_jours'] += $calc['renforts']['total_jours'];
            $report['totals']['renforts_heures'] += $calc['renforts']['total_heures'];
            $report['totals']['total_jours'] += $calc['total']['jours'];
            $report['totals']['total_heures'] += $calc['total']['heures'];
        }

        return $report;
    }
}
