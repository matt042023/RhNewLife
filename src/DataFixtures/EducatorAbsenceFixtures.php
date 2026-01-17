<?php

namespace App\DataFixtures;

use App\Entity\Absence;
use App\Entity\TypeAbsence;
use App\Entity\User;
use App\Repository\TypeAbsenceRepository;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class EducatorAbsenceFixtures extends Fixture implements DependentFixtureInterface
{
    public function __construct(
        private TypeAbsenceRepository $typeAbsenceRepository
    ) {}

    public function load(ObjectManager $manager): void
    {
        // Seed fixe pour reproductibilité
        mt_srand(12345);

        // Récupérer les types d'absence
        $typeCP = $this->typeAbsenceRepository->findOneBy(['code' => 'CP']);
        $typeMAL = $this->typeAbsenceRepository->findOneBy(['code' => 'MAL']);
        $typeCPSS = $this->typeAbsenceRepository->findOneBy(['code' => 'CPSS']);
        $typeRTT = $this->typeAbsenceRepository->findOneBy(['code' => 'RTT']);

        // Récupérer l'admin pour validation
        /** @var User $admin */
        $admin = $this->getReference('admin', User::class);

        // Tous les types d'absence disponibles
        $absenceTypes = array_filter([$typeCP, $typeMAL, $typeCPSS, $typeRTT]);

        $totalAbsences = 0;

        // Générer 1 absence par mois (Novembre 2025, Décembre 2025, Janvier 2026, Février 2026)
        $months = [
            ['year' => 2025, 'month' => 11, 'label' => 'Novembre 2025'],
            ['year' => 2025, 'month' => 12, 'label' => 'Décembre 2025'],
            ['year' => 2026, 'month' => 1, 'label' => 'Janvier 2026'],
            ['year' => 2026, 'month' => 2, 'label' => 'Février 2026'],
        ];

        foreach ($months as $monthData) {
            // Choisir un éducateur aléatoire (1 à 6)
            $educatorNum = mt_rand(1, 6);
            /** @var User $educator */
            $educator = $this->getReference("educator-{$educatorNum}", User::class);

            // Choisir un type d'absence aléatoire
            $absenceType = $absenceTypes[array_rand($absenceTypes)];

            // Générer une durée aléatoire (1 à 10 jours)
            $durationDays = mt_rand(1, 10);

            // Générer une date de début aléatoire dans le mois
            $monthStart = new \DateTime("{$monthData['year']}-{$monthData['month']}-01");
            $monthEnd = (clone $monthStart)->modify('last day of this month');
            $startDate = $this->randomWorkingDateInPeriod($monthStart, $monthEnd);

            // Calculer la date de fin
            $endDate = clone $startDate;
            $endDate->modify("+{$durationDays} days");

            // Créer l'absence
            $totalAbsences += $this->createAbsence(
                $manager,
                $educator,
                $absenceType,
                $startDate,
                $endDate,
                "Absence {$monthData['label']}",
                $admin
            );

            echo "✓ Absence créée pour {$educator->getFullName()} en {$monthData['label']} ({$absenceType->getLabel()}, {$durationDays} jours)\n";
        }

        $manager->flush();

        echo "\n✅ {$totalAbsences} absences créées avec succès !\n\n";
    }

    /**
     * Crée une absence et la persiste
     */
    private function createAbsence(
        ObjectManager $manager,
        User $user,
        TypeAbsence $type,
        \DateTimeInterface $start,
        \DateTimeInterface $end,
        string $reason,
        User $validator
    ): int {
        $absence = new Absence();
        $absence->setUser($user);
        $absence->setAbsenceType($type);
        $absence->setStartAt($start);
        $absence->setEndAt($end);
        $absence->setReason($reason);
        $absence->setStatus(Absence::STATUS_APPROVED);
        $absence->setValidatedBy($validator);
        $absence->setJustificationStatus(Absence::JUSTIF_NOT_REQUIRED);
        $absence->setAffectsPlanning(true);

        // Calcul des jours ouvrés
        $workingDays = $this->calculateWorkingDays($start, $end);
        $absence->setWorkingDaysCount($workingDays);

        $manager->persist($absence);

        return 1;
    }

    /**
     * Calcule le nombre de jours ouvrés entre deux dates
     */
    private function calculateWorkingDays(\DateTimeInterface $start, \DateTimeInterface $end): float
    {
        $workingDays = 0;
        /** @var \DateTime $current */
        $current = clone $start;

        while ($current <= $end) {
            $dayOfWeek = (int) $current->format('N'); // 1 = Lundi, 7 = Dimanche
            if ($dayOfWeek < 6) { // Lundi à Vendredi
                $workingDays++;
            }
            $current->modify('+1 day');
        }

        return (float) $workingDays;
    }

    /**
     * Génère une date aléatoire de jour ouvré entre deux dates
     */
    private function randomWorkingDateInPeriod(\DateTime $startPeriod, \DateTime $endPeriod): \DateTime
    {
        $attempts = 0;
        do {
            // Calculer un timestamp aléatoire entre les deux dates
            $startTimestamp = $startPeriod->getTimestamp();
            $endTimestamp = $endPeriod->getTimestamp();
            $randomTimestamp = mt_rand($startTimestamp, $endTimestamp);

            $date = new \DateTime();
            $date->setTimestamp($randomTimestamp);

            $dayOfWeek = (int) $date->format('N');
            $attempts++;
        } while ($dayOfWeek >= 6 && $attempts < 100); // Éviter les weekends

        return $date;
    }

    public function getDependencies(): array
    {
        return [
            EducatorFixtures::class,
            TypeAbsenceFixtures::class,
        ];
    }
}
