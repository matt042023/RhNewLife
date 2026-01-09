<?php

namespace App\Service\Planning;

use App\Entity\Affectation;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class PlanningAssignmentService
{
    public function __construct(
        private EntityManagerInterface $em,
        private PlanningConflictService $conflictService,
        private PlanningValidationService $validationService,
        private PlanningAvailabilityService $availabilityService
    ) {
    }

    /**
     * Assign a user to an affectation (drag & drop)
     * Returns non-blocking warnings
     */
    public function assignUserToAffectation(Affectation $affectation, User $user): array
    {
        // Affecter l'éducateur
        $affectation->setUser($user);

        // Auto-calculer les jours travaillés
        $workingDays = $this->calculateWorkingDays($affectation);
        $affectation->setJoursTravailes((int)$workingDays);

        // Vérifier conflits avec le service existant
        $this->conflictService->checkAndResolveConflicts($affectation);

        // Auto-sauvegarde
        $this->em->flush();

        // Récupérer warnings (non-bloquants)
        $warnings = $this->getValidationWarnings($affectation);

        return $warnings;
    }

    /**
     * Update affectation hours (drag edges to resize)
     */
    public function updateAffectationHours(Affectation $affectation, \DateTime $start, \DateTime $end): void
    {
        $affectation->setStartAt($start);
        $affectation->setEndAt($end);

        // Recalculer les jours travaillés après modification des heures
        $workingDays = $this->calculateWorkingDays($affectation);
        $affectation->setJoursTravailes((int)$workingDays);

        // Vérifier nouveaux conflits
        if ($affectation->getUser()) {
            $this->conflictService->checkAndResolveConflicts($affectation);
        }

        $this->em->flush();
    }

    /**
     * Calculate working days for an affectation based on 24h+3h tolerance rule.
     *
     * Rule: First 24h + 3h tolerance, then each additional 24h block with >3h = +1 day
     * Formula: if (hours < 7) return 0; else return ceil((hours - 3) / 24)
     *
     * Examples:
     * - 7h → 1 day
     * - 27h → 1 day
     * - 27h01 → 2 days
     * - 50h → 2 days
     * - 51h → 2 days
     * - 51h01 → 3 days
     * - 52h → 3 days
     */
    public function calculateWorkingDays(Affectation $affectation): float
    {
        $start = $affectation->getStartAt();
        $end = $affectation->getEndAt();

        if (!$start || !$end) {
            return 0;
        }

        $hoursDiff = ($end->getTimestamp() - $start->getTimestamp()) / 3600;

        // Si < 7h : ne compte pas
        if ($hoursDiff < 7) {
            return 0;
        }

        // Nouvelle règle : 24h + 3h de tolérance
        // Chaque tranche de 24h avec plus de 3h supplémentaires = 1 jour de plus
        // Formule : ceil((heures - 3) / 24)
        return (int) ceil(($hoursDiff - 3) / 24);
    }

    /**
     * Get all validation warnings for an affectation (non-blocking)
     */
    public function getValidationWarnings(Affectation $affectation): array
    {
        $warnings = [];

        // 1. Vérifier chevauchements absences/RDV
        $overlaps = $this->availabilityService->checkAbsenceOverlaps($affectation);
        foreach ($overlaps as $overlap) {
            $warnings[] = [
                'type' => $overlap['type'],
                'message' => sprintf(
                    "Chevauchement avec %s du %s au %s",
                    $overlap['label'],
                    $overlap['start']->format('d/m/Y H:i'),
                    $overlap['end']->format('d/m/Y H:i')
                ),
                'severity' => $overlap['severity']
            ];
        }

        // 2. Vérifier durée
        $hours = $this->calculateHours($affectation);
        if ($hours < 7) {
            $warnings[] = [
                'type' => 'duration_too_short',
                'message' => sprintf('Durée trop courte: %sh (minimum 7h)', $hours),
                'severity' => 'warning'
            ];
        } elseif ($hours > 72) {
            $warnings[] = [
                'type' => 'duration_too_long',
                'message' => sprintf('Durée très longue: %sh (maximum recommandé 72h)', $hours),
                'severity' => 'warning'
            ];
        }

        // 3. TODO: Vérifier limite 258 jours (nécessite AnnualDayCounterService)

        return $warnings;
    }

    /**
     * Calculate hours between start and end
     */
    private function calculateHours(Affectation $affectation): float
    {
        $start = $affectation->getStartAt();
        $end = $affectation->getEndAt();

        if (!$start || !$end) {
            return 0;
        }

        return ($end->getTimestamp() - $start->getTimestamp()) / 3600;
    }
}
