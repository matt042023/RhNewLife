<?php

namespace App\Service\Absence;

use App\Entity\Absence;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Service to handle absence impact on planning
 * Will be fully implemented in Phase 7 when Planning module is integrated
 */
class AbsencePlanningService
{
    public function __construct(
        private EntityManagerInterface $em,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Mark affectations as needing replacement
     * TODO: Implement in Phase 7 when Affectation entity exists
     */
    public function markAffectationsForReplacement(Absence $absence): void
    {
        if (!$absence->isAffectsPlanning()) {
            return;
        }

        // Placeholder: Will be implemented when Planning module is ready
        // This should:
        // 1. Find all Affectations for user during absence period
        // 2. Mark them with needsReplacement = true
        // 3. Set replacementReason = 'Absence validée'
        // 4. Notify planning admin

        $this->logger->info('Planning impact triggered (placeholder)', [
            'absence_id' => $absence->getId(),
            'user_id' => $absence->getUser()->getId(),
            'start' => $absence->getStartAt()->format('Y-m-d'),
            'end' => $absence->getEndAt()->format('Y-m-d'),
        ]);

        /* Example implementation for Phase 7:

        $affectations = $this->em->getRepository(Affectation::class)
            ->createQueryBuilder('a')
            ->where('a.user = :user')
            ->andWhere('a.date >= :start')
            ->andWhere('a.date <= :end')
            ->setParameter('user', $absence->getUser())
            ->setParameter('start', $absence->getStartAt())
            ->setParameter('end', $absence->getEndAt())
            ->getQuery()
            ->getResult();

        foreach ($affectations as $affectation) {
            $affectation
                ->setNeedsReplacement(true)
                ->setReplacementReason('Absence validée');
        }

        $this->em->flush();

        $this->logger->info('Planning updated for absence', [
            'absence_id' => $absence->getId(),
            'affected_affectations' => count($affectations),
        ]);
        */
    }

    /**
     * Get affected affectations for an absence
     * TODO: Implement in Phase 7
     *
     * @return array
     */
    public function getAffectedAffectations(Absence $absence): array
    {
        // Placeholder
        return [];

        /* Example implementation for Phase 7:

        return $this->em->getRepository(Affectation::class)
            ->createQueryBuilder('a')
            ->where('a.user = :user')
            ->andWhere('a.date >= :start')
            ->andWhere('a.date <= :end')
            ->setParameter('user', $absence->getUser())
            ->setParameter('start', $absence->getStartAt())
            ->setParameter('end', $absence->getEndAt())
            ->getQuery()
            ->getResult();
        */
    }

    /**
     * Propose replacement users for affected affectations
     * TODO: Implement in Phase 7
     *
     * @return array
     */
    public function proposeReplacements(Absence $absence): array
    {
        // Placeholder
        return [];

        /* Example implementation for Phase 7:

        $affectedAffectations = $this->getAffectedAffectations($absence);
        $replacements = [];

        foreach ($affectedAffectations as $affectation) {
            // Find available users with matching qualifications
            $availableUsers = $this->em->getRepository(User::class)
                ->findAvailableForReplacement(
                    $affectation->getDate(),
                    $affectation->getShift(),
                    $affectation->getVilla()
                );

            $replacements[$affectation->getId()] = $availableUsers;
        }

        return $replacements;
        */
    }

    /**
     * Check if absence creates planning conflicts
     * TODO: Implement in Phase 7
     */
    public function checkPlanningConflicts(Absence $absence): array
    {
        // Placeholder
        return [];

        /* Example implementation for Phase 7:

        $conflicts = [];
        $affectedAffectations = $this->getAffectedAffectations($absence);

        foreach ($affectedAffectations as $affectation) {
            // Check if villa would be left without educator
            $otherEducators = $this->em->getRepository(Affectation::class)
                ->findOtherEducatorsForSlot(
                    $affectation->getVilla(),
                    $affectation->getDate(),
                    $affectation->getShift(),
                    $absence->getUser()
                );

            if (count($otherEducators) === 0) {
                $conflicts[] = [
                    'affectation_id' => $affectation->getId(),
                    'villa' => $affectation->getVilla()->getName(),
                    'date' => $affectation->getDate()->format('Y-m-d'),
                    'shift' => $affectation->getShift(),
                    'severity' => 'critical',
                    'message' => 'Villa left without educator',
                ];
            }
        }

        return $conflicts;
        */
    }
}
