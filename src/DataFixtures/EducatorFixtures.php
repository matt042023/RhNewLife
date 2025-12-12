<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class EducatorFixtures extends Fixture
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher
    ) {}

    public function load(ObjectManager $manager): void
    {
        // Seed fixe pour reproductibilité
        mt_srand(12345);

        // Password commun pour tous les éducateurs
        $commonPassword = 'Educator123!@#';

        // ========================================
        // 1. THOMAS DUBOIS
        // ========================================
        $educator1 = new User();
        $educator1
            ->setEmail('thomas.dubois@rhnewlife.fr')
            ->setFirstName('Thomas')
            ->setLastName('Dubois')
            ->setPhone('06 12 34 56 01')
            ->setAddress('28 Rue du Tondu, 33000 Bordeaux')
            ->setPosition('Éducateur spécialisé')
            ->setStructure('Villa des Roses')
            ->setFamilyStatus('Marié')
            ->setChildren(2)
            ->setIban('FR76 3000 4000 0100 0000 001')
            ->setBic('BNPAFRPP')
            ->setStatus(User::STATUS_ACTIVE)
            ->setRoles(['ROLE_USER'])
            ->setCguAcceptedAt($this->randomDateInPast(90, 180)); // 3-6 mois

        $educator1->setPassword($this->passwordHasher->hashPassword($educator1, $commonPassword));
        $manager->persist($educator1);
        $this->addReference('educator-1', $educator1);

        // ========================================
        // 2. MARIE MARTIN
        // ========================================
        $educator2 = new User();
        $educator2
            ->setEmail('marie.martin@rhnewlife.fr')
            ->setFirstName('Marie')
            ->setLastName('Martin')
            ->setPhone('06 12 34 56 02')
            ->setAddress('15 Avenue Thiers, 33100 Bordeaux')
            ->setPosition('Éducatrice technique')
            ->setStructure('Foyer Le Phare')
            ->setFamilyStatus('Célibataire')
            ->setChildren(0)
            ->setIban('FR76 3000 4000 0100 0000 002')
            ->setBic('BNPAFRPP')
            ->setStatus(User::STATUS_ACTIVE)
            ->setRoles(['ROLE_USER'])
            ->setCguAcceptedAt($this->randomDateInPast(90, 180));

        $educator2->setPassword($this->passwordHasher->hashPassword($educator2, $commonPassword));
        $manager->persist($educator2);
        $this->addReference('educator-2', $educator2);

        // ========================================
        // 3. LUCAS PETIT
        // ========================================
        $educator3 = new User();
        $educator3
            ->setEmail('lucas.petit@rhnewlife.fr')
            ->setFirstName('Lucas')
            ->setLastName('Petit')
            ->setPhone('06 12 34 56 03')
            ->setAddress('42 Cours de la Marne, 33800 Bordeaux')
            ->setPosition('Moniteur éducateur')
            ->setStructure('Villa des Roses')
            ->setFamilyStatus('Pacsé')
            ->setChildren(1)
            ->setIban('FR76 3000 4000 0100 0000 003')
            ->setBic('BNPAFRPP')
            ->setStatus(User::STATUS_ACTIVE)
            ->setRoles(['ROLE_USER'])
            ->setCguAcceptedAt($this->randomDateInPast(90, 180));

        $educator3->setPassword($this->passwordHasher->hashPassword($educator3, $commonPassword));
        $manager->persist($educator3);
        $this->addReference('educator-3', $educator3);

        // ========================================
        // 4. ÉMILIE ROBERT
        // ========================================
        $educator4 = new User();
        $educator4
            ->setEmail('emilie.robert@rhnewlife.fr')
            ->setFirstName('Émilie')
            ->setLastName('Robert')
            ->setPhone('06 12 34 56 04')
            ->setAddress('8 Allée de Tourny, 33000 Bordeaux')
            ->setPosition('Éducatrice spécialisée')
            ->setStructure('Maison des Colibris')
            ->setFamilyStatus('Mariée')
            ->setChildren(3)
            ->setIban('FR76 3000 4000 0100 0000 004')
            ->setBic('BNPAFRPP')
            ->setStatus(User::STATUS_ACTIVE)
            ->setRoles(['ROLE_USER'])
            ->setCguAcceptedAt($this->randomDateInPast(90, 180));

        $educator4->setPassword($this->passwordHasher->hashPassword($educator4, $commonPassword));
        $manager->persist($educator4);
        $this->addReference('educator-4', $educator4);

        // ========================================
        // 5. ALEXANDRE MOREAU
        // ========================================
        $educator5 = new User();
        $educator5
            ->setEmail('alexandre.moreau@rhnewlife.fr')
            ->setFirstName('Alexandre')
            ->setLastName('Moreau')
            ->setPhone('06 12 34 56 05')
            ->setAddress('33 Rue Sainte-Catherine, 33000 Bordeaux')
            ->setPosition('Éducateur sportif')
            ->setStructure('Foyer Le Phare')
            ->setFamilyStatus('Célibataire')
            ->setChildren(0)
            ->setIban('FR76 3000 4000 0100 0000 005')
            ->setBic('BNPAFRPP')
            ->setStatus(User::STATUS_ACTIVE)
            ->setRoles(['ROLE_USER'])
            ->setCguAcceptedAt($this->randomDateInPast(90, 180));

        $educator5->setPassword($this->passwordHasher->hashPassword($educator5, $commonPassword));
        $manager->persist($educator5);
        $this->addReference('educator-5', $educator5);

        // ========================================
        // 6. JULIE SIMON
        // ========================================
        $educator6 = new User();
        $educator6
            ->setEmail('julie.simon@rhnewlife.fr')
            ->setFirstName('Julie')
            ->setLastName('Simon')
            ->setPhone('06 12 34 56 06')
            ->setAddress('19 Place de la Victoire, 33000 Bordeaux')
            ->setPosition('Éducatrice jeune enfant')
            ->setStructure('Maison des Colibris')
            ->setFamilyStatus('Mariée')
            ->setChildren(1)
            ->setIban('FR76 3000 4000 0100 0000 006')
            ->setBic('BNPAFRPP')
            ->setStatus(User::STATUS_ACTIVE)
            ->setRoles(['ROLE_USER'])
            ->setCguAcceptedAt($this->randomDateInPast(90, 180));

        $educator6->setPassword($this->passwordHasher->hashPassword($educator6, $commonPassword));
        $manager->persist($educator6);
        $this->addReference('educator-6', $educator6);

        // Sauvegarde en base
        $manager->flush();

        echo "\n✅ 6 éducateurs créés avec succès !\n\n";
        echo "📋 COMPTES ÉDUCATEURS :\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

        $educators = [
            ['Thomas Dubois', 'thomas.dubois@rhnewlife.fr', 'Éducateur spécialisé', 'Villa des Roses'],
            ['Marie Martin', 'marie.martin@rhnewlife.fr', 'Éducatrice technique', 'Foyer Le Phare'],
            ['Lucas Petit', 'lucas.petit@rhnewlife.fr', 'Moniteur éducateur', 'Villa des Roses'],
            ['Émilie Robert', 'emilie.robert@rhnewlife.fr', 'Éducatrice spécialisée', 'Maison des Colibris'],
            ['Alexandre Moreau', 'alexandre.moreau@rhnewlife.fr', 'Éducateur sportif', 'Foyer Le Phare'],
            ['Julie Simon', 'julie.simon@rhnewlife.fr', 'Éducatrice jeune enfant', 'Maison des Colibris'],
        ];

        foreach ($educators as $i => $edu) {
            $num = $i + 1;
            echo "👨‍🏫 ÉDUCATEUR #{$num}\n";
            echo "   Nom        : {$edu[0]}\n";
            echo "   Email      : {$edu[1]}\n";
            echo "   Password   : Educator123!@#\n";
            echo "   Position   : {$edu[2]}\n";
            echo "   Structure  : {$edu[3]}\n\n";
        }

        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        echo "🚀 Références 'educator-1' à 'educator-6' créées\n\n";
    }

    /**
     * Génère une date aléatoire dans le passé
     *
     * @param int $minDays Nombre minimum de jours dans le passé
     * @param int $maxDays Nombre maximum de jours dans le passé
     * @return \DateTime
     */
    private function randomDateInPast(int $minDays, int $maxDays): \DateTime
    {
        $daysAgo = mt_rand($minDays, $maxDays);
        return new \DateTime("-{$daysAgo} days");
    }
}
