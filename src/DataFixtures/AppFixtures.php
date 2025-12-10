<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher
    ) {}

    public function load(ObjectManager $manager): void
    {
        // ========================================
        // 1. ADMINISTRATEUR
        // ========================================
        $admin = new User();
        $admin
            ->setEmail('admin@rhnewlife.fr')
            ->setFirstName('Marie')
            ->setLastName('Dubois')
            ->setPhone('06 12 34 56 78')
            ->setAddress('123 Avenue des Champs-Élysées, 75008 Paris')
            ->setPosition('Administratrice RH')
            ->setStructure('Siège Social')
            ->setFamilyStatus('Mariée')
            ->setChildren(2)
            ->setIban('FR76 3000 6000 0112 3456 7890 189')
            ->setBic('AGRIFRPP')
            ->setStatus(User::STATUS_ACTIVE)
            ->setRoles(['ROLE_ADMIN'])
            ->setCguAcceptedAt(new \DateTime('-30 days'));

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
            ->setFirstName('Jean')
            ->setLastName('Martin')
            ->setPhone('06 23 45 67 89')
            ->setAddress('45 Rue de la République, 69002 Lyon')
            ->setPosition('Directeur')
            ->setStructure('Villa des Lilas')
            ->setFamilyStatus('Marié')
            ->setChildren(1)
            ->setIban('FR76 3000 3000 3000 0123 4567 890')
            ->setBic('SOGEFRPP')
            ->setStatus(User::STATUS_ACTIVE)
            ->setRoles(['ROLE_DIRECTOR'])
            ->setCguAcceptedAt(new \DateTime('-25 days'));

        $hashedPassword = $this->passwordHasher->hashPassword($director, 'Director123!@#');
        $director->setPassword($hashedPassword);

        $manager->persist($director);
        $this->addReference('director', $director);

        // ========================================
        // 3. ÉDUCATEUR
        // ========================================
        $educator = new User();
        $educator
            ->setEmail('educateur@rhnewlife.fr')
            ->setFirstName('Sophie')
            ->setLastName('Bernard')
            ->setPhone('06 34 56 78 90')
            ->setAddress('12 Rue Victor Hugo, 33000 Bordeaux')
            ->setPosition('Éducateur spécialisé')
            ->setStructure('Villa des Roses')
            ->setFamilyStatus('Célibataire')
            ->setChildren(0)
            ->setIban('FR76 1234 5678 9012 3456 7890 123')
            ->setBic('BNPAFRPP')
            ->setStatus(User::STATUS_ACTIVE)
            ->setRoles(['ROLE_USER'])
            ->setCguAcceptedAt(new \DateTime('-20 days'));

        $hashedPassword = $this->passwordHasher->hashPassword($educator, 'Educator123!@#');
        $educator->setPassword($hashedPassword);

        $manager->persist($educator);

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

        echo "👨‍🏫 ÉDUCATEUR\n";
        echo "   Email    : educateur@rhnewlife.fr\n";
        echo "   Password : Educator123!@#\n";
        echo "   Rôle     : ROLE_USER\n";
        echo "   Nom      : Sophie Bernard\n\n";

        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        echo "🚀 Vous pouvez maintenant vous connecter sur /login\n\n";
    }
}
