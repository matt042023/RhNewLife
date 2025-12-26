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

        // Récupérer l'admin pour validation
        /** @var User $admin */
        $admin = $this->getReference('admin', User::class);

        $totalAbsences = 0;

        // Générer les absences pour chaque éducateur
        for ($i = 1; $i <= 6; $i++) {
            /** @var User $educator */
            $educator = $this->getReference("educator-{$i}", User::class);

            echo "Génération absences pour {$educator->getFullName()}...\n";

            // A. Congés Payés (5 semaines = 25 jours)
            $totalAbsences += $this->generateVacations($manager, $educator, $i, $typeCP, $admin);

            // B. Arrêts Maladie (3 par éducateur)
            $totalAbsences += $this->generateSickLeaves($manager, $educator, $typeMAL, $admin);

            // C. Quelques congés sans solde (1-2 par éducateur)
            if (mt_rand(0, 1) === 1) {
                $totalAbsences += $this->generateUnpaidLeaves($manager, $educator, $typeCPSS, $admin);
            }
        }

        $manager->flush();

        echo "\n✅ {$totalAbsences} absences créées avec succès !\n\n";
    }

    /**
     * Génère les congés payés pour un éducateur
     */
    private function generateVacations(
        ObjectManager $manager,
        User $educator,
        int $educatorNum,
        TypeAbsence $typeCP,
        User $admin
    ): int {
        $count = 0;

        // Stratégies différentes par éducateur pour plus de variété
        switch ($educatorNum) {
            case 1: // Éducateur 1 : 3 semaines en août
                $count += $this->createAbsence($manager, $educator, $typeCP,
                    new \DateTime('2024-08-05'), new \DateTime('2024-08-25'),
                    'Congés d\'été', $admin);
                break;

            case 2: // Éducateur 2 : 2 semaines en juillet + 1 semaine en décembre
                $count += $this->createAbsence($manager, $educator, $typeCP,
                    new \DateTime('2024-07-08'), new \DateTime('2024-07-21'),
                    'Congés d\'été', $admin);
                $count += $this->createAbsence($manager, $educator, $typeCP,
                    new \DateTime('2024-12-23'), new \DateTime('2024-12-29'),
                    'Congés de fin d\'année', $admin);
                break;

            case 3: // Éducateur 3 : 1 semaine Pâques + 2 semaines été + 1 jour novembre
                $count += $this->createAbsence($manager, $educator, $typeCP,
                    new \DateTime('2024-04-08'), new \DateTime('2024-04-14'),
                    'Congés de printemps', $admin);
                $count += $this->createAbsence($manager, $educator, $typeCP,
                    new \DateTime('2024-07-22'), new \DateTime('2024-08-11'),
                    'Congés d\'été', $admin);
                $count += $this->createAbsence($manager, $educator, $typeCP,
                    new \DateTime('2024-11-01'), new \DateTime('2024-11-01'),
                    'Pont de la Toussaint', $admin);
                break;

            case 4: // Éducateur 4 : 2 semaines février + 2 semaines août
                $count += $this->createAbsence($manager, $educator, $typeCP,
                    new \DateTime('2024-02-12'), new \DateTime('2024-02-25'),
                    'Congés d\'hiver', $admin);
                $count += $this->createAbsence($manager, $educator, $typeCP,
                    new \DateTime('2024-08-12'), new \DateTime('2024-08-25'),
                    'Congés d\'été', $admin);
                break;

            case 5: // Éducateur 5 : Répartition régulière (4 x 1 semaine)
                $count += $this->createAbsence($manager, $educator, $typeCP,
                    new \DateTime('2024-03-11'), new \DateTime('2024-03-15'),
                    'Congés mars', $admin);
                $count += $this->createAbsence($manager, $educator, $typeCP,
                    new \DateTime('2024-06-10'), new \DateTime('2024-06-16'),
                    'Congés juin', $admin);
                $count += $this->createAbsence($manager, $educator, $typeCP,
                    new \DateTime('2024-09-02'), new \DateTime('2024-09-08'),
                    'Congés septembre', $admin);
                $count += $this->createAbsence($manager, $educator, $typeCP,
                    new \DateTime('2024-10-28'), new \DateTime('2024-11-03'),
                    'Congés octobre-novembre', $admin);
                break;

            case 6: // Éducateur 6 : 3 semaines été + ponts
                $count += $this->createAbsence($manager, $educator, $typeCP,
                    new \DateTime('2024-07-29'), new \DateTime('2024-08-18'),
                    'Congés d\'été', $admin);
                $count += $this->createAbsence($manager, $educator, $typeCP,
                    new \DateTime('2024-05-09'), new \DateTime('2024-05-10'),
                    'Pont de l\'Ascension', $admin);
                $count += $this->createAbsence($manager, $educator, $typeCP,
                    new \DateTime('2024-11-11'), new \DateTime('2024-11-11'),
                    '11 novembre', $admin);
                break;
        }

        return $count;
    }

    /**
     * Génère 3 arrêts maladie avec durées aléatoires 1-7 jours
     */
    private function generateSickLeaves(
        ObjectManager $manager,
        User $educator,
        TypeAbsence $typeMAL,
        User $admin
    ): int {
        $count = 0;
        $reasons = [
            'Grippe saisonnière',
            'Gastro-entérite',
            'Migraine sévère',
            'Angine',
            'Bronchite',
            'Lombalgie aiguë',
            'Syndrome grippal',
        ];

        for ($i = 0; $i < 3; $i++) {
            // Durée aléatoire 1-7 jours
            $durationDays = mt_rand(1, 7);

            // Date aléatoire dans l'année (éviter les périodes de congés)
            $startDate = $this->randomWorkingDate(2024);
            $endDate = clone $startDate;
            $endDate->modify("+{$durationDays} days");

            // 80% validés, 20% en attente
            $isApproved = mt_rand(1, 100) <= 80;
            $status = $isApproved ? Absence::STATUS_APPROVED : Absence::STATUS_PENDING;
            $justifStatus = $isApproved ? Absence::JUSTIF_VALIDATED : Absence::JUSTIF_PENDING;

            $absence = new Absence();
            $absence->setUser($educator);
            $absence->setAbsenceType($typeMAL);
            $absence->setStartAt($startDate);
            $absence->setEndAt($endDate);
            $absence->setReason($reasons[array_rand($reasons)]);
            $absence->setStatus($status);
            $absence->setJustificationStatus($justifStatus);
            $absence->setAffectsPlanning(true);

            // Calcul des jours ouvrés
            $workingDays = $this->calculateWorkingDays($startDate, $endDate);
            $absence->setWorkingDaysCount($workingDays);

            if ($isApproved) {
                $absence->setValidatedBy($admin);
            } else {
                // Deadline 48h après le début
                $deadline = clone $startDate;
                $deadline->modify('+2 days');
                $absence->setJustificationDeadline($deadline);
            }

            $manager->persist($absence);
            $count++;
        }

        return $count;
    }

    /**
     * Génère 1-2 congés sans solde
     */
    private function generateUnpaidLeaves(
        ObjectManager $manager,
        User $educator,
        TypeAbsence $typeCPSS,
        User $admin
    ): int {
        $count = 0;
        $absences = [
            ['Raisons personnelles', 1],
            ['Convenance personnelle', 2],
            ['Projet personnel', 3],
        ];

        $numAbsences = mt_rand(1, 2);
        for ($i = 0; $i < $numAbsences; $i++) {
            $absenceData = $absences[array_rand($absences)];
            $reason = $absenceData[0];
            $durationDays = $absenceData[1];

            $startDate = $this->randomWorkingDate(2024);
            $endDate = clone $startDate;
            $endDate->modify("+{$durationDays} days");

            $count += $this->createAbsence($manager, $educator, $typeCPSS,
                $startDate, $endDate, $reason, $admin);
        }

        return $count;
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
     * Génère une date aléatoire de jour ouvré dans l'année
     */
    private function randomWorkingDate(int $year): \DateTime
    {
        $attempts = 0;
        do {
            $month = mt_rand(1, 12);
            $day = mt_rand(1, 28); // Éviter les problèmes de fin de mois
            $date = new \DateTime("{$year}-{$month}-{$day}");
            $dayOfWeek = (int) $date->format('N');
            $attempts++;
        } while ($dayOfWeek >= 6 && $attempts < 50); // Éviter les weekends

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
