<?php

namespace App\DataFixtures;

use App\Entity\User;
use App\Entity\VisiteMedicale;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class VisiteMedicaleFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        // Seed fixe pour reproductibilité
        mt_srand(67890);

        $totalVisites = 0;

        // Générer des visites pour chaque éducateur
        for ($i = 1; $i <= 6; $i++) {
            /** @var User $educator */
            $educator = $this->getReference("educator-{$i}", User::class);

            echo "Génération visites médicales pour {$educator->getFullName()}...\n";

            // A. Visite d'embauche (obligatoire) - EFFECTUEE
            $totalVisites += $this->createHiringVisit($manager, $educator, $i);

            // B. Visites périodiques (1-2 par éducateur selon l'ancienneté) - EFFECTUEE
            $totalVisites += $this->createPeriodicVisits($manager, $educator, $i);

            // C. Quelques visites de reprise (1 sur 3 éducateurs) - EFFECTUEE
            if ($i % 3 === 0) {
                $totalVisites += $this->createReturnToWorkVisit($manager, $educator);
            }

            // D. Rare: visite à la demande (1 sur 6 éducateurs) - EFFECTUEE
            if ($i === 6) {
                $totalVisites += $this->createOnDemandVisit($manager, $educator);
            }

            // E. Visites PROGRAMMEES pour tester le workflow (premiers 3 éducateurs)
            if ($i <= 3) {
                $totalVisites += $this->createProgrammeeVisit($manager, $educator, $i);
            }
        }

        $manager->flush();

        echo "\n✅ {$totalVisites} visites médicales créées avec succès !\n\n";
    }

    /**
     * Visite d'embauche (obligatoire à l'entrée)
     */
    private function createHiringVisit(ObjectManager $manager, User $educator, int $educatorNum): int
    {
        // Date d'embauche simulée (il y a 1-5 ans)
        $yearsAgo = mt_rand(1, 5);
        $hiringDate = new \DateTime("-{$yearsAgo} years");

        $visite = new VisiteMedicale();
        $visite->setUser($educator);
        $visite->setType(VisiteMedicale::TYPE_EMBAUCHE);
        $visite->setStatus(VisiteMedicale::STATUS_EFFECTUEE);
        $visite->setVisitDate($hiringDate);

        // Validité : 2 ans pour visite d'embauche
        $expiryDate = clone $hiringDate;
        $expiryDate->modify('+2 years');
        $visite->setExpiryDate($expiryDate);

        $visite->setMedicalOrganization($this->randomMedicalOrganization());
        $visite->setAptitude(VisiteMedicale::APTITUDE_APTE);
        $visite->setObservations(null);

        $manager->persist($visite);

        return 1;
    }

    /**
     * Visites périodiques (tous les 2-5 ans selon les cas)
     */
    private function createPeriodicVisits(ObjectManager $manager, User $educator, int $educatorNum): int
    {
        $count = 0;

        // Nombre de visites périodiques selon l'éducateur (simuler différentes anciennetés)
        $numVisits = match($educatorNum) {
            1, 2 => 2, // Anciens: 2 visites périodiques
            3, 4 => 1, // Moyens: 1 visite
            default => 0, // Récents: pas encore de périodique
        };

        for ($i = 0; $i < $numVisits; $i++) {
            // Date de visite (étalées dans le temps)
            $yearsAgo = mt_rand(1, 4);
            $visitDate = new \DateTime("-{$yearsAgo} years +{$i} years");

            $visite = new VisiteMedicale();
            $visite->setUser($educator);
            $visite->setType(VisiteMedicale::TYPE_PERIODIQUE);
            $visite->setStatus(VisiteMedicale::STATUS_EFFECTUEE);
            $visite->setVisitDate($visitDate);

            // Validité : 2-5 ans selon l'âge
            $validityYears = mt_rand(2, 5);
            $expiryDate = clone $visitDate;
            $expiryDate->modify("+{$validityYears} years");
            $visite->setExpiryDate($expiryDate);

            $visite->setMedicalOrganization($this->randomMedicalOrganization());

            // 80% apte, 15% apte avec réserves, 5% inapte
            $rand = mt_rand(1, 100);
            if ($rand <= 80) {
                $visite->setAptitude(VisiteMedicale::APTITUDE_APTE);
                $visite->setObservations(null);
            } elseif ($rand <= 95) {
                $visite->setAptitude(VisiteMedicale::APTITUDE_APTE_AVEC_RESERVE);
                $visite->setObservations($this->randomRestriction());
            } else {
                $visite->setAptitude(VisiteMedicale::APTITUDE_INAPTE);
                $visite->setObservations('Inapte temporairement suite à problème de santé. Réévaluation dans 3 mois.');
            }

            $manager->persist($visite);
            $count++;
        }

        // Créer une visite proche de l'expiration pour 2 éducateurs (pour tester les alertes)
        if (in_array($educatorNum, [2, 4])) {
            $visitDate = new \DateTime('-23 months'); // Il y a presque 2 ans
            $expiryDate = new \DateTime('+1 month'); // Expire dans 1 mois

            $visite = new VisiteMedicale();
            $visite->setUser($educator);
            $visite->setType(VisiteMedicale::TYPE_PERIODIQUE);
            $visite->setStatus(VisiteMedicale::STATUS_EFFECTUEE);
            $visite->setVisitDate($visitDate);
            $visite->setExpiryDate($expiryDate);
            $visite->setMedicalOrganization($this->randomMedicalOrganization());
            $visite->setAptitude(VisiteMedicale::APTITUDE_APTE);
            $visite->setObservations(null);

            $manager->persist($visite);
            $count++;
        }

        // Créer une visite expirée pour 1 éducateur (pour tester les alertes)
        if ($educatorNum === 3) {
            $visitDate = new \DateTime('-3 years');
            $expiryDate = new \DateTime('-2 months'); // Expirée depuis 2 mois

            $visite = new VisiteMedicale();
            $visite->setUser($educator);
            $visite->setType(VisiteMedicale::TYPE_PERIODIQUE);
            $visite->setStatus(VisiteMedicale::STATUS_EFFECTUEE);
            $visite->setVisitDate($visitDate);
            $visite->setExpiryDate($expiryDate);
            $visite->setMedicalOrganization($this->randomMedicalOrganization());
            $visite->setAptitude(VisiteMedicale::APTITUDE_APTE_AVEC_RESERVE);
            $visite->setObservations('Port de charges lourdes limité à 15kg.');

            $manager->persist($visite);
            $count++;
        }

        return $count;
    }

    /**
     * Visite de reprise (après arrêt maladie)
     */
    private function createReturnToWorkVisit(ObjectManager $manager, User $educator): int
    {
        $visitDate = new \DateTime('-6 months');
        $expiryDate = clone $visitDate;
        $expiryDate->modify('+1 year'); // Validité 1 an

        $visite = new VisiteMedicale();
        $visite->setUser($educator);
        $visite->setType(VisiteMedicale::TYPE_REPRISE);
        $visite->setStatus(VisiteMedicale::STATUS_EFFECTUEE);
        $visite->setVisitDate($visitDate);
        $visite->setExpiryDate($expiryDate);
        $visite->setMedicalOrganization($this->randomMedicalOrganization());
        $visite->setAptitude(VisiteMedicale::APTITUDE_APTE_AVEC_RESERVE);
        $visite->setObservations('Reprise progressive. Pas de travail de nuit pendant 3 mois.');

        $manager->persist($visite);

        return 1;
    }

    /**
     * Visite à la demande (rare)
     */
    private function createOnDemandVisit(ObjectManager $manager, User $educator): int
    {
        $visitDate = new \DateTime('-3 months');
        $expiryDate = clone $visitDate;
        $expiryDate->modify('+6 months'); // Validité 6 mois

        $visite = new VisiteMedicale();
        $visite->setUser($educator);
        $visite->setType(VisiteMedicale::TYPE_DEMANDE);
        $visite->setStatus(VisiteMedicale::STATUS_EFFECTUEE);
        $visite->setVisitDate($visitDate);
        $visite->setExpiryDate($expiryDate);
        $visite->setMedicalOrganization($this->randomMedicalOrganization());
        $visite->setAptitude(VisiteMedicale::APTITUDE_APTE);
        $visite->setObservations('Visite demandée par le salarié pour vérification. Aucune restriction.');

        $manager->persist($visite);

        return 1;
    }

    /**
     * Organisme médical aléatoire
     */
    private function randomMedicalOrganization(): string
    {
        $organizations = [
            'Médecine du Travail ACMS',
            'Centre de Santé au Travail (CST)',
            'ASTIL - Service de Santé au Travail',
            'AISMT 13 - Association Interentreprises',
            'SIST - Service Interentreprises',
            'Médecine du Travail Provence',
        ];

        return $organizations[array_rand($organizations)];
    }

    /**
     * Restriction aléatoire pour apte avec réserves
     */
    private function randomRestriction(): string
    {
        $restrictions = [
            'Port de charges lourdes limité à 10kg.',
            'Éviter les postures prolongées debout. Privilégier la rotation des tâches.',
            'Pas de travail en hauteur. Aménagement du poste à prévoir.',
            'Limitation des horaires de nuit. Adapter les plannings.',
            'Éviter les efforts répétitifs du membre supérieur droit.',
            'Aménagement ergonomique du poste de travail recommandé.',
        ];

        return $restrictions[array_rand($restrictions)];
    }

    /**
     * Visite programmée (pour tester le workflow)
     */
    private function createProgrammeeVisit(ObjectManager $manager, User $educator, int $educatorNum): int
    {
        // Dates dans le futur
        $scheduledDays = [7, 14, 21]; // 7, 14, 21 jours dans le futur
        $daysAhead = $scheduledDays[$educatorNum - 1] ?? 7;

        $visite = new VisiteMedicale();
        $visite->setUser($educator);

        // Alterner les types pour les visites programmées
        $types = [
            VisiteMedicale::TYPE_PERIODIQUE,
            VisiteMedicale::TYPE_EMBAUCHE,
            VisiteMedicale::TYPE_REPRISE
        ];
        $visite->setType($types[$educatorNum - 1]);

        // Status programmée - pas encore effectuée
        $visite->setStatus(VisiteMedicale::STATUS_PROGRAMMEE);

        // Pas de données médicales tant que pas effectuée
        $visite->setVisitDate(null);
        $visite->setExpiryDate(null);
        $visite->setAptitude(null);
        $visite->setObservations(null);
        $visite->setMedicalOrganization(null);

        $manager->persist($visite);

        return 1;
    }

    public function getDependencies(): array
    {
        return [
            EducatorFixtures::class,
        ];
    }
}
