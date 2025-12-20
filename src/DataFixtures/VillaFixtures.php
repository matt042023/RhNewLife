<?php

namespace App\DataFixtures;

use App\Entity\Villa;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class VillaFixtures extends Fixture
{
    public const VILLA_LILAS = 'villa_lilas';
    public const VILLA_ROSES = 'villa_roses';
    public const VILLA_MARGUERITES = 'villa_marguerites';

    public function load(ObjectManager $manager): void
    {
        $villaLilas = new Villa();
        $villaLilas
            ->setNom('Villa des Lilas')
            ->setColor('#9333EA'); // Purple

        $manager->persist($villaLilas);
        $this->addReference(self::VILLA_LILAS, $villaLilas);

        $villaRoses = new Villa();
        $villaRoses
            ->setNom('Villa des Roses')
            ->setColor('#EC4899'); // Pink

        $manager->persist($villaRoses);
        $this->addReference(self::VILLA_ROSES, $villaRoses);

        $villaMarguerites = new Villa();
        $villaMarguerites
            ->setNom('Villa des Marguerites')
            ->setColor('#F59E0B'); // Amber

        $manager->persist($villaMarguerites);
        $this->addReference(self::VILLA_MARGUERITES, $villaMarguerites);

        $manager->flush();

        echo "\n✅ Villas créées : Lilas, Roses, Marguerites\n\n";
    }
}
