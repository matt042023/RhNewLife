<?php

namespace App\DataFixtures;

use App\Entity\User;
use App\Entity\Villa;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class EducatorFixtures extends Fixture implements DependentFixtureInterface
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher
    ) {}

    public function getDependencies(): array
    {
        return [VillaFixtures::class];
    }

    public function load(ObjectManager $manager): void
    {
        // Seed fixe pour reproductibilité
        mt_srand(12345);

        // Password commun pour tous les éducateurs
        $commonPassword = 'Educator123!@#';

        // ========================================
        // VILLA DES LILAS - 4 ÉDUCATEURS
        // ========================================

        // 1. THOMAS DUBOIS - Villa des Lilas
        $educator1 = new User();
        $educator1
            ->setEmail('thomas.dubois@rhnewlife.fr')
            ->setFirstName('Thomas')
            ->setLastName('Dubois')
            ->setPhone('06 12 34 56 01')
            ->setAddress('28 Rue du Tondu, 33000 Bordeaux')
            ->setPosition('Éducateur spécialisé')
            ->setVilla($this->getReference(VillaFixtures::VILLA_LILAS, Villa::class))
            ->setColor('#3B82F6')
            ->setFamilyStatus('Marié')
            ->setChildren(2)
            ->setIban('FR76 3000 4000 0100 0000 001')
            ->setBic('BNPAFRPP')
            ->setStatus(User::STATUS_ACTIVE)
            ->setRoles(['ROLE_USER'])
            ->setCguAcceptedAt($this->randomDateInPast(90, 180));

        $educator1->getHealth()->setMutuelleEnabled(true)
            ->setMutuelleNom('Mutuelle Pro')
            ->setMutuelleFormule('Double Effet')
            ->setMutuelleDateFin(new \DateTime('+1 year'))
            ->setPrevoyanceEnabled(true)
            ->setPrevoyanceNom('AXA');

        $educator1->setPassword($this->passwordHasher->hashPassword($educator1, $commonPassword));
        $manager->persist($educator1);
        $this->addReference('educator-1', $educator1);

        // 2. MARIE MARTIN - Villa des Lilas
        $educator2 = new User();
        $educator2
            ->setEmail('marie.martin@rhnewlife.fr')
            ->setFirstName('Marie')
            ->setLastName('Martin')
            ->setPhone('06 12 34 56 02')
            ->setAddress('15 Avenue Thiers, 33100 Bordeaux')
            ->setPosition('Éducatrice technique')
            ->setVilla($this->getReference(VillaFixtures::VILLA_LILAS, Villa::class))
            ->setColor('#EC4899')
            ->setFamilyStatus('Célibataire')
            ->setChildren(0)
            ->setIban('FR76 3000 4000 0100 0000 002')
            ->setBic('BNPAFRPP')
            ->setStatus(User::STATUS_ACTIVE)
            ->setRoles(['ROLE_USER'])
            ->setCguAcceptedAt($this->randomDateInPast(90, 180));

        $educator2->getHealth()->setMutuelleEnabled(true)
            ->setMutuelleNom('MGEN')
            ->setMutuelleFormule('Référence')
            ->setMutuelleDateFin(new \DateTime('+2 years'))
            ->setPrevoyanceEnabled(false);

        $educator2->setPassword($this->passwordHasher->hashPassword($educator2, $commonPassword));
        $manager->persist($educator2);
        $this->addReference('educator-2', $educator2);

        // 3. LUCAS PETIT - Villa des Lilas
        $educator3 = new User();
        $educator3
            ->setEmail('lucas.petit@rhnewlife.fr')
            ->setFirstName('Lucas')
            ->setLastName('Petit')
            ->setPhone('06 12 34 56 03')
            ->setAddress('42 Cours de la Marne, 33800 Bordeaux')
            ->setPosition('Moniteur éducateur')
            ->setVilla($this->getReference(VillaFixtures::VILLA_LILAS, Villa::class))
            ->setColor('#10B981')
            ->setFamilyStatus('Pacsé')
            ->setChildren(1)
            ->setIban('FR76 3000 4000 0100 0000 003')
            ->setBic('BNPAFRPP')
            ->setStatus(User::STATUS_ACTIVE)
            ->setRoles(['ROLE_USER'])
            ->setCguAcceptedAt($this->randomDateInPast(90, 180));

        $educator3->getHealth()->setMutuelleEnabled(false)
            ->setPrevoyanceEnabled(true)
            ->setPrevoyanceNom('Malakoff Humanis');

        $educator3->setPassword($this->passwordHasher->hashPassword($educator3, $commonPassword));
        $manager->persist($educator3);
        $this->addReference('educator-3', $educator3);

        // 4. ÉMILIE ROBERT - Villa des Lilas
        $educator4 = new User();
        $educator4
            ->setEmail('emilie.robert@rhnewlife.fr')
            ->setFirstName('Émilie')
            ->setLastName('Robert')
            ->setPhone('06 12 34 56 04')
            ->setAddress('8 Allée de Tourny, 33000 Bordeaux')
            ->setPosition('Éducatrice spécialisée')
            ->setVilla($this->getReference(VillaFixtures::VILLA_LILAS, Villa::class))
            ->setColor('#8B5CF6')
            ->setFamilyStatus('Mariée')
            ->setChildren(3)
            ->setIban('FR76 3000 4000 0100 0000 004')
            ->setBic('BNPAFRPP')
            ->setStatus(User::STATUS_ACTIVE)
            ->setRoles(['ROLE_USER'])
            ->setCguAcceptedAt($this->randomDateInPast(90, 180));

        $educator4->getHealth()->setMutuelleEnabled(true)
            ->setMutuelleNom('Swiss Life')
            ->setMutuelleFormule('Santé Retraite')
            ->setMutuelleDateFin(new \DateTime('+1 year'))
            ->setPrevoyanceEnabled(true)
            ->setPrevoyanceNom('Swiss Life Prévoyance');

        $educator4->setPassword($this->passwordHasher->hashPassword($educator4, $commonPassword));
        $manager->persist($educator4);
        $this->addReference('educator-4', $educator4);

        // ========================================
        // VILLA DES ROSES - 4 ÉDUCATEURS
        // ========================================

        // 5. ALEXANDRE MOREAU - Villa des Roses
        $educator5 = new User();
        $educator5
            ->setEmail('alexandre.moreau@rhnewlife.fr')
            ->setFirstName('Alexandre')
            ->setLastName('Moreau')
            ->setPhone('06 12 34 56 05')
            ->setAddress('33 Rue Sainte-Catherine, 33000 Bordeaux')
            ->setPosition('Éducateur sportif')
            ->setVilla($this->getReference(VillaFixtures::VILLA_ROSES, Villa::class))
            ->setColor('#F59E0B')
            ->setFamilyStatus('Célibataire')
            ->setChildren(0)
            ->setIban('FR76 3000 4000 0100 0000 005')
            ->setBic('BNPAFRPP')
            ->setStatus(User::STATUS_ACTIVE)
            ->setRoles(['ROLE_USER'])
            ->setCguAcceptedAt($this->randomDateInPast(90, 180));

        $educator5->getHealth()->setMutuelleEnabled(true)
            ->setMutuelleNom('Alan')
            ->setMutuelleFormule('Blue')
            ->setMutuelleDateFin(new \DateTime('+1 year'))
            ->setPrevoyanceEnabled(false);

        $educator5->setPassword($this->passwordHasher->hashPassword($educator5, $commonPassword));
        $manager->persist($educator5);
        $this->addReference('educator-5', $educator5);

        // 6. JULIE SIMON - Villa des Roses
        $educator6 = new User();
        $educator6
            ->setEmail('julie.simon@rhnewlife.fr')
            ->setFirstName('Julie')
            ->setLastName('Simon')
            ->setPhone('06 12 34 56 06')
            ->setAddress('19 Place de la Victoire, 33000 Bordeaux')
            ->setPosition('Éducatrice jeune enfant')
            ->setVilla($this->getReference(VillaFixtures::VILLA_ROSES, Villa::class))
            ->setColor('#EF4444')
            ->setFamilyStatus('Mariée')
            ->setChildren(1)
            ->setIban('FR76 3000 4000 0100 0000 006')
            ->setBic('BNPAFRPP')
            ->setStatus(User::STATUS_ACTIVE)
            ->setRoles(['ROLE_USER'])
            ->setCguAcceptedAt($this->randomDateInPast(90, 180));

        $educator6->getHealth()->setMutuelleEnabled(false)
            ->setPrevoyanceEnabled(false);

        $educator6->setPassword($this->passwordHasher->hashPassword($educator6, $commonPassword));
        $manager->persist($educator6);
        $this->addReference('educator-6', $educator6);

        // 7. ANTOINE BERNARD - Villa des Roses
        $educator7 = new User();
        $educator7
            ->setEmail('antoine.bernard@rhnewlife.fr')
            ->setFirstName('Antoine')
            ->setLastName('Bernard')
            ->setPhone('06 12 34 56 07')
            ->setAddress('67 Rue Fondaudège, 33000 Bordeaux')
            ->setPosition('Moniteur éducateur')
            ->setVilla($this->getReference(VillaFixtures::VILLA_ROSES, Villa::class))
            ->setColor('#06B6D4')
            ->setFamilyStatus('Marié')
            ->setChildren(2)
            ->setIban('FR76 3000 4000 0100 0000 007')
            ->setBic('BNPAFRPP')
            ->setStatus(User::STATUS_ACTIVE)
            ->setRoles(['ROLE_USER'])
            ->setCguAcceptedAt($this->randomDateInPast(90, 180));

        $educator7->getHealth()->setMutuelleEnabled(true)
            ->setMutuelleNom('Generali')
            ->setMutuelleFormule('Hospi 100')
            ->setMutuelleDateFin(new \DateTime('+6 months'))
            ->setPrevoyanceEnabled(true)
            ->setPrevoyanceNom('Generali Prévoyance');

        $educator7->setPassword($this->passwordHasher->hashPassword($educator7, $commonPassword));
        $manager->persist($educator7);
        $this->addReference('educator-7', $educator7);

        // 8. CAMILLE LAURENT - Villa des Roses
        $educator8 = new User();
        $educator8
            ->setEmail('camille.laurent@rhnewlife.fr')
            ->setFirstName('Camille')
            ->setLastName('Laurent')
            ->setPhone('06 12 34 56 08')
            ->setAddress('91 Rue Judaïque, 33000 Bordeaux')
            ->setPosition('Éducatrice spécialisée')
            ->setVilla($this->getReference(VillaFixtures::VILLA_ROSES, Villa::class))
            ->setColor('#84CC16')
            ->setFamilyStatus('Pacsée')
            ->setChildren(0)
            ->setIban('FR76 3000 4000 0100 0000 008')
            ->setBic('BNPAFRPP')
            ->setStatus(User::STATUS_ACTIVE)
            ->setRoles(['ROLE_USER'])
            ->setCguAcceptedAt($this->randomDateInPast(90, 180));

        $educator8->getHealth()->setMutuelleEnabled(true)
            ->setMutuelleNom('Apicil')
            ->setMutuelleFormule('Sereinis')
            ->setMutuelleDateFin(new \DateTime('+1 year'))
            ->setPrevoyanceEnabled(true)
            ->setPrevoyanceNom('Apicil Prévoyance');

        $educator8->setPassword($this->passwordHasher->hashPassword($educator8, $commonPassword));
        $manager->persist($educator8);
        $this->addReference('educator-8', $educator8);

        // Sauvegarde en base
        $manager->flush();

        echo "\n✅ 8 éducateurs créés avec succès !\n\n";
        echo "📋 COMPTES ÉDUCATEURS :\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

        $educators = [
            ['Thomas Dubois', 'thomas.dubois@rhnewlife.fr', 'Éducateur spécialisé', 'Villa des Lilas'],
            ['Marie Martin', 'marie.martin@rhnewlife.fr', 'Éducatrice technique', 'Villa des Lilas'],
            ['Lucas Petit', 'lucas.petit@rhnewlife.fr', 'Moniteur éducateur', 'Villa des Lilas'],
            ['Émilie Robert', 'emilie.robert@rhnewlife.fr', 'Éducatrice spécialisée', 'Villa des Lilas'],
            ['Alexandre Moreau', 'alexandre.moreau@rhnewlife.fr', 'Éducateur sportif', 'Villa des Roses'],
            ['Julie Simon', 'julie.simon@rhnewlife.fr', 'Éducatrice jeune enfant', 'Villa des Roses'],
            ['Antoine Bernard', 'antoine.bernard@rhnewlife.fr', 'Moniteur éducateur', 'Villa des Roses'],
            ['Camille Laurent', 'camille.laurent@rhnewlife.fr', 'Éducatrice spécialisée', 'Villa des Roses'],
        ];

        foreach ($educators as $i => $edu) {
            $num = $i + 1;
            echo "👨‍🏫 ÉDUCATEUR #{$num}\n";
            echo "   Nom        : {$edu[0]}\n";
            echo "   Email      : {$edu[1]}\n";
            echo "   Password   : Educator123!@#\n";
            echo "   Position   : {$edu[2]}\n";
            echo "   Villa      : {$edu[3]}\n\n";
        }

        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        echo "🚀 Références 'educator-1' à 'educator-8' créées\n\n";
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
