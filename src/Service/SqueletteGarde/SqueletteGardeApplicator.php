<?php

namespace App\Service\SqueletteGarde;

use App\Entity\SqueletteGarde;
use App\Entity\PlanningMonth;
use App\Entity\Affectation;
use App\Entity\Villa;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Service to apply a template to a specific planning month
 */
class SqueletteGardeApplicator
{
    public function __construct(
        private EntityManagerInterface $em,
        private LoggerInterface $logger
    ) {}

    /**
     * Apply a template to a planning month
     *
     * @return array{created: int, affectations: Affectation[]}
     */
    public function applyToPlanning(
        SqueletteGarde $squelette,
        PlanningMonth $planningMois,
        ?\DateTime $periodStart = null,
        ?\DateTime $periodEnd = null,
        bool $renfortOnly = false
    ): array {
        $config = $squelette->getConfigurationArray();
        $affectations = [];

        // Get the first and last day of the month
        $year = $planningMois->getAnnee();
        $month = $planningMois->getMois();
        $firstDay = new \DateTime(sprintf('%d-%02d-01', $year, $month));
        $lastDay = (clone $firstDay)->modify('last day of this month');

        // If period constraints are provided, apply them
        if ($periodStart && $periodStart > $firstDay) {
            $firstDay = clone $periodStart;
        }
        if ($periodEnd && $periodEnd < $lastDay) {
            $lastDay = clone $periodEnd;
        }

        // Find the Monday of the week containing the first day of the month
        // This may be in the previous month to ensure we don't skip weeks
        $weekStart = clone $firstDay;
        while ($weekStart->format('N') != 1) {
            $weekStart->modify('-1 day');
        }

        // Loop through all weeks in the period
        while ($weekStart <= $lastDay) {
            // Apply creneaux_garde for this week (skip if renfortOnly mode)
            if (!$renfortOnly) {
                foreach ($config['creneaux_garde'] ?? [] as $creneau) {
                    $affectation = $this->createAffectationFromCreneauGarde(
                        $creneau,
                        $planningMois,
                        $weekStart,
                        $squelette->getNom()
                    );
                    if ($affectation) {
                        // Split if spans multiple months
                        $segments = $this->splitAffectationByMonth($affectation);

                        foreach ($segments as $segment) {
                            $segmentStart = $segment->getStartAt();

                            // Include segment if it STARTS within this planning's month
                            $segmentYear = (int)$segmentStart->format('Y');
                            $segmentMonth = (int)$segmentStart->format('m');

                            if ($segmentYear === $planningMois->getAnnee()
                                && $segmentMonth === $planningMois->getMois()) {
                                $affectations[] = $segment;
                            }
                        }
                    }
                }
            }

            // Apply creneaux_renfort for this week
            foreach ($config['creneaux_renfort'] ?? [] as $creneau) {
                $affectation = $this->createAffectationFromCreneauRenfort(
                    $creneau,
                    $planningMois,
                    $weekStart,
                    $squelette->getNom(),
                    $renfortOnly // Propager le flag renfortOnly depuis le scope d'application
                );
                if ($affectation) {
                    // Split if spans multiple months
                    $segments = $this->splitAffectationByMonth($affectation);

                    foreach ($segments as $segment) {
                        $segmentStart = $segment->getStartAt();

                        // Include segment if it STARTS within this planning's month
                        $segmentYear = (int)$segmentStart->format('Y');
                        $segmentMonth = (int)$segmentStart->format('m');

                        if ($segmentYear === $planningMois->getAnnee()
                            && $segmentMonth === $planningMois->getMois()) {
                            $affectations[] = $segment;
                        }
                    }
                }
            }

            // Move to next week (next Monday)
            $weekStart->modify('+7 days');
        }

        // Persist all
        foreach ($affectations as $affectation) {
            $this->em->persist($affectation);
        }

        // Record usage
        $squelette->incrementUtilisation();

        $this->em->flush();

        $this->logger->info('SqueletteGarde applied to planning', [
            'squelette_id' => $squelette->getId(),
            'planning_id' => $planningMois->getId(),
            'affectations_created' => count($affectations),
        ]);

        return [
            'created' => count($affectations),
            'affectations' => $affectations
        ];
    }

    /**
     * Apply a template to a period spanning multiple months and optionally multiple villas
     *
     * @param SqueletteGarde $squelette The template to apply
     * @param \DateTime $startDate Start of the period
     * @param \DateTime $endDate End of the period
     * @param Villa|null $villa Specific villa (if scope is 'villa')
     * @param bool $allVillas Apply to all villas (if scope is 'all')
     * @param bool $renfortOnly Only apply renfort affectations (if scope is 'renfort')
     *
     * @return array{created: int, plannings: PlanningMonth[]}
     */
    public function applyToPeriod(
        SqueletteGarde $squelette,
        \DateTime $startDate,
        \DateTime $endDate,
        ?Villa $villa = null,
        bool $allVillas = false,
        bool $renfortOnly = false
    ): array {
        $totalCreated = 0;
        $plannings = [];

        // Calculate all months between startDate and endDate
        $currentMonth = (clone $startDate)->modify('first day of this month');
        $endMonth = (clone $endDate)->modify('first day of this month');

        while ($currentMonth <= $endMonth) {
            $year = (int) $currentMonth->format('Y');
            $month = (int) $currentMonth->format('m');

            // Determine which villas to apply to
            $villasToProcess = [];

            if ($allVillas) {
                // Get all villas from database
                $villasToProcess = $this->em->getRepository(Villa::class)->findAll();
            } elseif ($villa !== null) {
                // Single villa
                $villasToProcess = [$villa];
            } elseif ($renfortOnly) {
                // For renfort partagé, we need at least one villa to attach the planning
                // Use the first villa or create a special handling
                $villasToProcess = $this->em->getRepository(Villa::class)->findAll();
                // For renfort, we'll only process the first villa as it's shared
                $villasToProcess = array_slice($villasToProcess, 0, 1);
            }

            // Process each villa
            foreach ($villasToProcess as $villaToProcess) {
                // Get or create PlanningMonth
                $planningMonth = $this->em->getRepository(PlanningMonth::class)
                    ->findOneBy([
                        'villa' => $villaToProcess,
                        'annee' => $year,
                        'mois' => $month
                    ]);

                if (!$planningMonth) {
                    $planningMonth = new PlanningMonth();
                    $planningMonth
                        ->setVilla($villaToProcess)
                        ->setAnnee($year)
                        ->setMois($month)
                        ->setStatut(PlanningMonth::STATUS_DRAFT);

                    $this->em->persist($planningMonth);
                    $this->em->flush(); // Flush to get ID for relations
                }

                // Apply template to this planning with period constraints
                $result = $this->applyToPlanning($squelette, $planningMonth, $startDate, $endDate, $renfortOnly);

                $totalCreated += $result['created'];
                $plannings[] = $planningMonth;

                $this->logger->info('Template applied to planning', [
                    'squelette_id' => $squelette->getId(),
                    'villa_id' => $villaToProcess->getId(),
                    'year' => $year,
                    'month' => $month,
                    'affectations_created' => $result['created']
                ]);
            }

            // Move to next month
            $currentMonth->modify('+1 month');
        }

        return [
            'created' => $totalCreated,
            'plannings' => $plannings
        ];
    }

    private function createAffectationFromCreneauGarde(
        array $creneau,
        PlanningMonth $planningMois,
        \DateTime $weekStart,
        string $templateName
    ): ?Affectation {
        // Calculate actual dates from jour_debut + heure_debut + duree_heures
        $startAt = (clone $weekStart)->modify('+' . ($creneau['jour_debut'] - 1) . ' days');
        $startAt->setTime($creneau['heure_debut'], 0, 0);

        $endAt = (clone $startAt)->modify('+' . $creneau['duree_heures'] . ' hours');

        $affectation = new Affectation();
        $affectation
            ->setPlanningMois($planningMois)
            ->setVilla($planningMois->getVilla())
            ->setStartAt($startAt)
            ->setEndAt($endAt)
            ->setType($creneau['type'] ?? Affectation::TYPE_GARDE_48H)
            ->setIsFromSquelette(true)
            ->setCommentaire('Créé depuis template: ' . $templateName);

        return $affectation;
    }

    private function createAffectationFromCreneauRenfort(
        array $creneau,
        PlanningMonth $planningMois,
        \DateTime $weekStart,
        string $templateName,
        bool $renfortOnly = false
    ): ?Affectation {
        $day = (clone $weekStart)->modify('+' . ($creneau['jour'] - 1) . ' days');

        $startAt = (clone $day)->setTime($creneau['heure_debut'], 0, 0);
        $endAt = (clone $day)->setTime($creneau['heure_fin'], 0, 0);

        // Déterminer la villa selon le contexte et la configuration du créneau
        $villa = null;
        if (isset($creneau['villa_id']) && $creneau['villa_id']) {
            // Renfort villa-spécifique défini dans le template
            $villa = $this->em->getRepository(Villa::class)->find($creneau['villa_id']);
        } elseif ($renfortOnly) {
            // Scope "renfort seulement": pas de villa (centre-complet)
            $villa = null;
        } else {
            // Application normale: utiliser villa du planning (compatibilité avec données existantes)
            $villa = $planningMois->getVilla();
        }

        $affectation = new Affectation();
        $affectation
            ->setPlanningMois($planningMois)
            ->setVilla($villa) // Peut être null pour renfort centre-complet
            ->setStartAt($startAt)
            ->setEndAt($endAt)
            ->setType(Affectation::TYPE_RENFORT)
            ->setIsFromSquelette(true)
            ->setCommentaire('Créé depuis template: ' . $templateName . ' - ' . ($creneau['label'] ?? 'Renfort'));

        return $affectation;
    }

    /**
     * Splits an affectation that spans multiple months into separate affectations
     *
     * Important: A guard shift is counted for the month where it STARTS, unless it truly
     * spans multiple calendar days across month boundaries (e.g., 48h shift from Jan 31 to Feb 2).
     *
     * Rules:
     * - 24h shift (e.g., Jan 31 7h → Feb 1 7h): Counted entirely for January (no split)
     * - 48h shift (e.g., Jan 31 7h → Feb 2 7h): Split into 2 segments (Jan 31-Feb 1, Feb 1-Feb 2)
     *
     * @param Affectation $affectation The original affectation to split
     * @return Affectation[] Array of affectations (1 if same month, 2+ if spanning)
     */
    private function splitAffectationByMonth(Affectation $affectation): array
    {
        $startAt = $affectation->getStartAt();
        $endAt = $affectation->getEndAt();

        // Check if same month
        if ($startAt->format('Y-m') === $endAt->format('Y-m')) {
            return [$affectation]; // No split needed
        }

        // Calculate the number of calendar days spanned
        $startDay = (int)$startAt->format('d');
        $endDay = (int)$endAt->format('d');
        $startMonth = (int)$startAt->format('m');
        $endMonth = (int)$endAt->format('m');
        $startYear = (int)$startAt->format('Y');
        $endYear = (int)$endAt->format('Y');

        // Special case: 24h shift that ends on the 1st of next month at the same time
        // Example: Jan 31 7h → Feb 1 7h (24h shift)
        // This should count entirely for January (the month where it started)
        if ($endDay === 1 && $startAt->format('H:i') === $endAt->format('H:i')) {
            // This is a 24h shift ending on day 1 of next month
            // Keep it as a single affectation for the start month
            return [$affectation];
        }

        // For shifts that truly span multiple days across months (e.g., 48h+)
        // Split by day boundaries, counting each full day for its respective month
        $affectations = [];
        $currentSegmentStart = clone $startAt;

        while ($currentSegmentStart < $endAt) {
            // Calculate the end of the current day (same time next day)
            $nextDayAtSameTime = (clone $currentSegmentStart)->modify('+1 day');

            // If the shift ends before the next day at same time, this is the last segment
            $currentSegmentEnd = ($nextDayAtSameTime < $endAt) ? $nextDayAtSameTime : clone $endAt;

            // Get or create PlanningMonth for this segment
            $segmentYear = (int)$currentSegmentStart->format('Y');
            $segmentMonth = (int)$currentSegmentStart->format('m');
            $planningMonth = $this->getOrCreatePlanningMonth(
                $affectation->getVilla(),
                $segmentYear,
                $segmentMonth
            );

            // Create affectation segment
            $segment = new Affectation();
            $segment
                ->setPlanningMois($planningMonth)
                ->setVilla($affectation->getVilla())
                ->setStartAt(clone $currentSegmentStart)
                ->setEndAt($currentSegmentEnd)
                ->setType($affectation->getType())
                ->setIsFromSquelette($affectation->isIsFromSquelette())
                ->setCommentaire($affectation->getCommentaire() . ' [Partie ' . (count($affectations) + 1) . ']');

            // Calculate working days for THIS segment only (should be 1 per 24h block)
            $segment->setJoursTravailes($this->calculateWorkingDaysForSegment($segment));

            $affectations[] = $segment;

            // Move to next segment
            $currentSegmentStart = $currentSegmentEnd;
        }

        // Mark all segments with metadata if we created multiple
        if (count($affectations) > 1) {
            for ($i = 0; $i < count($affectations); $i++) {
                $affectations[$i]
                    ->setIsSegmented(true)
                    ->setSegmentNumber($i + 1)
                    ->setTotalSegments(count($affectations));
            }
        }

        return $affectations;
    }

    /**
     * Get or create a PlanningMonth for a given villa, year, and month
     */
    private function getOrCreatePlanningMonth(Villa $villa, int $year, int $month): PlanningMonth
    {
        $planning = $this->em->getRepository(PlanningMonth::class)
            ->findOneBy(['villa' => $villa, 'annee' => $year, 'mois' => $month]);

        if (!$planning) {
            $planning = new PlanningMonth();
            $planning
                ->setVilla($villa)
                ->setAnnee($year)
                ->setMois($month)
                ->setStatut(PlanningMonth::STATUS_DRAFT);

            $this->em->persist($planning);
            $this->em->flush();
        }

        return $planning;
    }

    /**
     * Calculate working days for a specific affectation segment
     * Uses the same formula as PlanningAssignmentService but for the exact segment duration
     */
    private function calculateWorkingDaysForSegment(Affectation $affectation): int
    {
        $start = $affectation->getStartAt();
        $end = $affectation->getEndAt();

        $hoursDiff = ($end->getTimestamp() - $start->getTimestamp()) / 3600;

        if ($hoursDiff < 7) {
            return 0;
        }

        return (int) ceil(($hoursDiff - 3) / 24);
    }
}
