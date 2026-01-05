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

        // Recalculer si besoin (ex: jours travaillés)
        // TODO: Ajouter calcul jours travaillés

        // Vérifier nouveaux conflits
        if ($affectation->getUser()) {
            $this->conflictService->checkAndResolveConflicts($affectation);
        }

        $this->em->flush();
    }

    /**
     * Calculate working days for an affectation
     * Règle: 1 journée commencée = 1 jour décompté
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

        // Arrondi supérieur par tranche de 24h entamée
        return ceil($hoursDiff / 24);
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
