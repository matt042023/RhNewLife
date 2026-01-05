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
        ?\DateTime $periodEnd = null
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

        // Find the first Monday of the month (or closest)
        $weekStart = clone $firstDay;
        while ($weekStart->format('N') != 1) {
            $weekStart->modify('+1 day');
        }

        // Loop through all weeks in the period
        while ($weekStart <= $lastDay) {
            // Apply creneaux_garde for this week
            foreach ($config['creneaux_garde'] ?? [] as $creneau) {
                $affectation = $this->createAffectationFromCreneauGarde(
                    $creneau,
                    $planningMois,
                    $weekStart,
                    $squelette->getNom()
                );
                if ($affectation) {
                    // Include affectation if it STARTS within the period (even if it ends after)
                    $affectationStart = $affectation->getStartAt();

                    if ($affectationStart >= $firstDay && $affectationStart <= (clone $lastDay)->modify('+1 day')) {
                        $affectations[] = $affectation;
                    }
                }
            }

            // Apply creneaux_renfort for this week
            foreach ($config['creneaux_renfort'] ?? [] as $creneau) {
                $affectation = $this->createAffectationFromCreneauRenfort(
                    $creneau,
                    $planningMois,
                    $weekStart,
                    $squelette->getNom()
                );
                if ($affectation) {
                    // Include affectation if it STARTS within the period
                    $affectationStart = $affectation->getStartAt();

                    if ($affectationStart >= $firstDay && $affectationStart <= (clone $lastDay)->modify('+1 day')) {
                        $affectations[] = $affectation;
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
                $result = $this->applyToPlanning($squelette, $planningMonth, $startDate, $endDate);

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
        string $templateName
    ): ?Affectation {
        $day = (clone $weekStart)->modify('+' . ($creneau['jour'] - 1) . ' days');

        $startAt = (clone $day)->setTime($creneau['heure_debut'], 0, 0);
        $endAt = (clone $day)->setTime($creneau['heure_fin'], 0, 0);

        $affectation = new Affectation();
        $affectation
            ->setPlanningMois($planningMois)
            ->setVilla($planningMois->getVilla())
            ->setStartAt($startAt)
            ->setEndAt($endAt)
            ->setType(Affectation::TYPE_RENFORT)
            ->setIsFromSquelette(true)
            ->setCommentaire('Créé depuis template: ' . $templateName . ' - ' . ($creneau['label'] ?? 'Renfort'));

        return $affectation;
    }
}
