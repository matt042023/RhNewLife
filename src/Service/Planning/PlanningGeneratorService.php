<?php

namespace App\Service\Planning;

use App\Entity\Affectation;
use App\Entity\PlanningMonth;
use App\Entity\Villa;
use Doctrine\ORM\EntityManagerInterface;

class PlanningGeneratorService
{
    public function __construct(
        private EntityManagerInterface $em
    ) {
    }

    public function generateSkeleton(Villa $villa, int $year, int $month): PlanningMonth
    {
        // 1. Check if planning already exists
        $planning = $this->em->getRepository(PlanningMonth::class)->findOneBy([
            'villa' => $villa,
            'annee' => $year,
            'mois' => $month,
        ]);

        if (!$planning) {
            $planning = new PlanningMonth();
            $planning->setVilla($villa);
            $planning->setAnnee($year);
            $planning->setMois($month);
            $this->em->persist($planning);
        }

        // 2. Clear existing skeleton affectations (if regenerating)
        // Note: In a real scenario, we might want to keep manual changes.
        // For now, we assume a fresh generation or a full reset of skeleton items.
        foreach ($planning->getAffectations() as $existingAff) {
            if ($existingAff->isIsFromSquelette()) {
                $this->em->remove($existingAff);
            }
        }
        
        // Flush to remove old ones before adding new ones
        $this->em->flush();

        // 3. Generate slots
        $startDate = new \DateTime(sprintf('%d-%02d-01', $year, $month));
        $endDate = (clone $startDate)->modify('last day of this month');

        $currentDate = clone $startDate;

        // Find the first Monday of the month (or previous month if needed to start the cycle)
        // For simplicity, we'll start generating from the 1st day, 
        // but in reality, the 48h cycle might need to align with previous month.
        // Here we implement the requested pattern:
        // Lundi 7h -> Mercredi 7h
        // Mercredi 7h -> Jeudi 7h
        // Jeudi 7h -> Samedi 7h
        // Samedi 7h -> Lundi 7h
        
        // We iterate day by day
        while ($currentDate <= $endDate) {
            $dayOfWeek = (int) $currentDate->format('N'); // 1 (Mon) to 7 (Sun)
            
            // Define start hour (7h usually, 8h on weekends/holidays/vacations)
            // TODO: Implement holiday/vacation check logic
            $startHour = 7;
            if ($dayOfWeek >= 6) { // Weekend
                $startHour = 8;
            }

            $startAt = (clone $currentDate)->setTime($startHour, 0);

            // Cycle logic
            if ($dayOfWeek === 1) { // Lundi
                // Lundi 7h -> Mercredi 7h (48h)
                $endAt = (clone $startAt)->modify('+2 days');
                $this->createAffectation($planning, $villa, $startAt, $endAt, Affectation::TYPE_GARDE_48H);
            } elseif ($dayOfWeek === 3) { // Mercredi
                // Mercredi 7h -> Jeudi 7h (24h)
                $endAt = (clone $startAt)->modify('+1 day');
                $this->createAffectation($planning, $villa, $startAt, $endAt, Affectation::TYPE_GARDE_24H);

                // Renfort: Mercredi 11h-19h
                $renfortStart = (clone $currentDate)->setTime(11, 0);
                $renfortEnd = (clone $currentDate)->setTime(19, 0);
                $this->createAffectation($planning, $villa, $renfortStart, $renfortEnd, Affectation::TYPE_RENFORT);
            } elseif ($dayOfWeek === 4) { // Jeudi
                // Jeudi 7h -> Samedi 7h (48h)
                $endAt = (clone $startAt)->modify('+2 days');
                // Adjust end time for Saturday (8h)
                $endAt->setTime(8, 0); 
                $this->createAffectation($planning, $villa, $startAt, $endAt, Affectation::TYPE_GARDE_48H);
            } elseif ($dayOfWeek === 6) { // Samedi
                // Samedi 7h (actually 8h) -> Lundi 7h (48h)
                $endAt = (clone $startAt)->modify('+2 days');
                $endAt->setTime(7, 0); // Back to 7h on Monday
                $this->createAffectation($planning, $villa, $startAt, $endAt, Affectation::TYPE_GARDE_48H);

                // Renfort: Samedi 10h-18h
                $renfortStart = (clone $currentDate)->setTime(10, 0);
                $renfortEnd = (clone $currentDate)->setTime(18, 0);
                $this->createAffectation($planning, $villa, $renfortStart, $renfortEnd, Affectation::TYPE_RENFORT);
            }

            $currentDate->modify('+1 day');
        }

        $this->em->flush();

        return $planning;
    }

    private function createAffectation(PlanningMonth $planning, Villa $villa, \DateTimeInterface $start, \DateTimeInterface $end, string $type): void
    {
        // Ensure we don't create affectations starting after the month end (overlap is fine)
        // But the cycle logic above iterates within the month.
        
        $affectation = new Affectation();
        $affectation->setPlanningMois($planning);
        $affectation->setVilla($villa);
        $affectation->setStartAt($start);
        $affectation->setEndAt($end);
        $affectation->setType($type);
        $affectation->setIsFromSquelette(true);
        $affectation->setStatut(Affectation::STATUS_DRAFT);

        $this->em->persist($affectation);
    }
}
