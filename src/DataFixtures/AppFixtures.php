<?php

namespace App\DataFixtures;

use App\Entity\User;
use App\Entity\Villa;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture implements DependentFixtureInterface
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
        // ========================================
        // 1. ADMINISTRATEUR
        // ========================================
        $admin = new User();
        $admin
            ->setEmail('admin@rhnewlife.fr')
            ->setFirstName('Melanie')
            ->setLastName('Adjovi')
            ->setPhone('06 12 34 56 78')
            ->setAddress('123 Avenue des Champs-Élysées, 75008 Paris')
            ->setPosition('Administratrice RH')
            ->setMatricule('ADM001')
            ->setHiringDate(new \DateTime('2020-01-15'))
            ->setFamilyStatus('Mariée')
            ->setChildren(2)
            ->setIban('FR76 3000 6000 0112 3456 7890 189')
            ->setBic('AGRIFRPP')
            ->setStatus(User::STATUS_ACTIVE)
            ->setRoles(['ROLE_ADMIN'])
            ->setCguAcceptedAt(new \DateTime('-30 days'));

        $adminHealth = $admin->getHealth();
        $adminHealth->setMutuelleEnabled(true)
            ->setMutuelleNom('Harmonie Mutuelle')
            ->setMutuelleFormule('Formule Bronze')
            ->setMutuelleDateFin(new \DateTime('2026-12-31'))
            ->setPrevoyanceEnabled(true)
            ->setPrevoyanceNom('AXA Prévoyance');

        $hashedPassword = $this->passwordHasher->hashPassword($admin, 'Admin123!@#');
        $admin->setPassword($hashedPassword);

        $manager->persist($admin);
        $this->addReference('admin', $admin);

        // ========================================
        // 2. DIRECTEUR
        // ========================================
        $director = new User();
        $director
            ->setEmail('directeur@rhnewlife.fr')
            ->setFirstName('Fernand')
            ->setLastName('Adjovi')
            ->setPhone('06 23 45 67 89')
            ->setAddress('45 Rue de la République, 69002 Lyon')
            ->setPosition('Directeur')
            ->setMatricule('DIR001')
            ->setHiringDate(new \DateTime('2019-09-01'))
            ->setVilla($this->getReference(VillaFixtures::VILLA_LILAS, Villa::class))
            ->setFamilyStatus('Marié')
            ->setChildren(1)
            ->setIban('FR76 3000 3000 3000 0123 4567 890')
            ->setBic('SOGEFRPP')
            ->setStatus(User::STATUS_ACTIVE)
            ->setRoles(['ROLE_DIRECTOR'])
            ->setCguAcceptedAt(new \DateTime('-25 days'));

        $directorHealth = $director->getHealth();
        $directorHealth->setMutuelleEnabled(false)
            ->setPrevoyanceEnabled(true)
            ->setPrevoyanceNom('AXA Prévoyance');

        $hashedPassword = $this->passwordHasher->hashPassword($director, 'Director123!@#');
        $director->setPassword($hashedPassword);

        $manager->persist($director);
        $this->addReference('director', $director);

        // Sauvegarde en base
        $manager->flush();

        echo "\n✅ Fixtures chargées avec succès !\n\n";
        echo "📋 COMPTES CRÉÉS :\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

        echo "👑 ADMINISTRATEUR\n";
        echo "   Email    : admin@rhnewlife.fr\n";
        echo "   Password : Admin123!@#\n";
        echo "   Rôle     : ROLE_ADMIN\n";
        echo "   Nom      : Marie Dubois\n\n";

        echo "🏢 DIRECTEUR\n";
        echo "   Email    : directeur@rhnewlife.fr\n";
        echo "   Password : Director123!@#\n";
        echo "   Rôle     : ROLE_DIRECTOR\n";
        echo "   Nom      : Jean Martin\n\n";


        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        echo "🚀 Vous pouvez maintenant vous connecter sur /login\n\n";
    }
}
