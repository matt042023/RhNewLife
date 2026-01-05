<?php

namespace App\Service\Planning;

use App\DTO\Planning\ValidationResult;
use App\Entity\Affectation;
use App\Entity\PlanningMonth;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class PlanningValidationService
{
    public function __construct(
        private EntityManagerInterface $em,
        private PlanningAvailabilityService $availabilityService
    ) {
    }

    /**
     * Validate a complete planning with all checks
     */
    public function validatePlanning(PlanningMonth $planning): ValidationResult
    {
        $result = new ValidationResult(true);

        // 1. Vérifier couverture 24/24
        $gaps = $this->checkCoverageGaps($planning);
        foreach ($gaps as $gap) {
            $result->addWarning(
                'coverage_gap',
                sprintf(
                    "Trou de couverture %s: %s - %s",
                    $gap['villa'],
                    $gap['start']->format('d/m/Y H:i'),
                    $gap['end']->format('d/m/Y H:i')
                ),
                'error'
            );
        }

        // 2. Conflits horaires (éducateur sur 2 villas)
        $conflicts = $this->checkScheduleConflicts($planning);
        foreach ($conflicts as $conflict) {
            $result->addWarning(
                'schedule_conflict',
                sprintf(
                    "%s affecté simultanément sur %d villas",
                    $conflict['user'],
                    count($conflict['affectations'])
                ),
                'error'
            );
        }

        // 3. Vérifier limite 258 jours pour chaque éducateur
        $year = $planning->getAnnee();
        $usersChecked = [];

        foreach ($planning->getAffectations() as $affectation) {
            if ($user = $affectation->getUser()) {
                $userId = $user->getId();
                if (!in_array($userId, $usersChecked)) {
                    $limits = $this->checkAnnualLimits($user, $year);
                    foreach ($limits as $limit) {
                        $result->addWarning(
                            $limit['type'],
                            $limit['message'],
                            $limit['severity']
                        );
                    }
                    $usersChecked[] = $userId;
                }
            }
        }

        // 4. Chevauchements absences/RDV
        foreach ($planning->getAffectations() as $affectation) {
            $overlaps = $this->checkAbsenceOverlaps($affectation);
            foreach ($overlaps as $overlap) {
                $result->addWarning(
                    $overlap['type'],
                    sprintf(
                        "%s : chevauchement avec %s du %s au %s",
                        $affectation->getUser()?->getFullName() ?? 'Utilisateur',
                        $overlap['label'],
                        $overlap['start']->format('d/m/Y H:i'),
                        $overlap['end']->format('d/m/Y H:i')
                    ),
                    $overlap['severity']
                );
            }
        }

        return $result;
    }

    /**
     * Check for coverage gaps (no educator assigned)
     */
    public function checkCoverageGaps(PlanningMonth $planning): array
    {
        $gaps = [];

        // TODO: Implémenter la logique de détection des trous
        // Pour chaque villa, vérifier qu'il y a toujours au moins un éducateur affecté

        return $gaps;
    }

    /**
     * Check for schedule conflicts (educator on 2 villas at same time)
     */
    public function checkScheduleConflicts(PlanningMonth $planning): array
    {
        $conflicts = [];
        $affectations = $planning->getAffectations()->toArray();

        // Grouper par éducateur
        $byUser = [];
        foreach ($affectations as $affectation) {
            if ($user = $affectation->getUser()) {
                $userId = $user->getId();
                if (!isset($byUser[$userId])) {
                    $byUser[$userId] = [
                        'user' => $user->getFullName(),
                        'affectations' => []
                    ];
                }
                $byUser[$userId]['affectations'][] = $affectation;
            }
        }

        // Vérifier chevauchements pour chaque éducateur
        foreach ($byUser as $userId => $data) {
            $userAffectations = $data['affectations'];

            for ($i = 0; $i < count($userAffectations); $i++) {
                for ($j = $i + 1; $j < count($userAffectations); $j++) {
                    $aff1 = $userAffectations[$i];
                    $aff2 = $userAffectations[$j];

                    // Vérifier si chevauchement
                    if ($this->hasOverlap($aff1, $aff2)) {
                        // Autoriser si c'est un renfort (peut chevaucher)
                        if ($aff1->getType() === Affectation::TYPE_RENFORT ||
                            $aff2->getType() === Affectation::TYPE_RENFORT) {
                            continue;
                        }

                        $conflicts[] = [
                            'user' => $data['user'],
                            'affectations' => [$aff1, $aff2]
                        ];
                    }
                }
            }
        }

        return $conflicts;
    }

    /**
     * Check annual limits (258 days)
     */
    public function checkAnnualLimits(User $user, int $year): array
    {
        $warnings = [];

        // TODO: Intégrer avec AnnualDayCounterService quand il sera créé
        // Pour l'instant, retourner un tableau vide

        return $warnings;
    }

    /**
     * Check absence overlaps for an affectation
     */
    public function checkAbsenceOverlaps(Affectation $affectation): array
    {
        return $this->availabilityService->checkAbsenceOverlaps($affectation);
    }

    /**
     * Check if two affectations overlap
     */
    private function hasOverlap(Affectation $aff1, Affectation $aff2): bool
    {
        $start1 = $aff1->getStartAt();
        $end1 = $aff1->getEndAt();
        $start2 = $aff2->getStartAt();
        $end2 = $aff2->getEndAt();

        return ($start1 < $end2 && $end1 > $start2);
    }
}
