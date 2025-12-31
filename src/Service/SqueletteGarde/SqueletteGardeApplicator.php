<?php

namespace App\Service\SqueletteGarde;

use App\Entity\SqueletteGarde;
use App\Entity\PlanningMonth;
use App\Entity\Affectation;
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
        PlanningMonth $planningMois
    ): array {
        $config = $squelette->getConfigurationArray();
        $affectations = [];

        // Get the first day of the month
        $year = $planningMois->getAnnee();
        $month = $planningMois->getMois();
        $firstDay = new \DateTime(sprintf('%d-%02d-01', $year, $month));

        // Find the first Monday of the month (or closest)
        $weekStart = clone $firstDay;
        while ($weekStart->format('N') != 1) {
            $weekStart->modify('+1 day');
        }

        // Apply creneaux_garde
        foreach ($config['creneaux_garde'] ?? [] as $creneau) {
            $affectation = $this->createAffectationFromCreneauGarde(
                $creneau,
                $planningMois,
                $weekStart,
                $squelette->getNom()
            );
            if ($affectation) {
                $affectations[] = $affectation;
            }
        }

        // Apply creneaux_renfort
        foreach ($config['creneaux_renfort'] ?? [] as $creneau) {
            $affectation = $this->createAffectationFromCreneauRenfort(
                $creneau,
                $planningMois,
                $weekStart,
                $squelette->getNom()
            );
            if ($affectation) {
                $affectations[] = $affectation;
            }
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
