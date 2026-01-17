<?php

namespace App\DataFixtures;

use App\Entity\Document;
use App\Entity\TypeAbsence;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class TypeAbsenceFixtures extends Fixture
{
    public const TYPE_CP = 'CP';
    public const TYPE_MAL = 'MAL';
    public const TYPE_AT = 'AT';
    public const TYPE_CPSS = 'CPSS';

    public function load(ObjectManager $manager): void
    {
        $types = [
            [
                'code' => self::TYPE_CP,
                'label' => 'Congés payés',
                'affectsPlanning' => true,
                'deductFromCounter' => true,
                'requiresJustification' => false,
                'justificationDeadlineDays' => null,
                'documentType' => null,
                'active' => true,
            ],
            [
                'code' => self::TYPE_MAL,
                'label' => 'Congé maladie',
                'affectsPlanning' => true,
                'deductFromCounter' => false,
                'requiresJustification' => true,
                'justificationDeadlineDays' => 2, // 48 heures
                'documentType' => Document::TYPE_MEDICAL_CERTIFICATE,
                'active' => true,
            ],
            [
                'code' => self::TYPE_AT,
                'label' => 'Accident du travail',
                'affectsPlanning' => true,
                'deductFromCounter' => false,
                'requiresJustification' => true,
                'justificationDeadlineDays' => 1, // 24 heures
                'documentType' => Document::TYPE_MEDICAL_CERTIFICATE,
                'active' => true,
            ],
            [
                'code' => self::TYPE_CPSS,
                'label' => 'Congé sans solde',
                'affectsPlanning' => true,
                'deductFromCounter' => false,
                'requiresJustification' => false,
                'justificationDeadlineDays' => null,
                'documentType' => null,
                'active' => true,
            ],
        ];

        foreach ($types as $index => $typeData) {
            $typeAbsence = new TypeAbsence();
            $typeAbsence
                ->setCode($typeData['code'])
                ->setLabel($typeData['label'])
                ->setAffectsPlanning($typeData['affectsPlanning'])
                ->setDeductFromCounter($typeData['deductFromCounter'])
                ->setRequiresJustification($typeData['requiresJustification'])
                ->setJustificationDeadlineDays($typeData['justificationDeadlineDays'])
                ->setDocumentType($typeData['documentType'])
                ->setActive($typeData['active']);

            $manager->persist($typeAbsence);

            // Référence pour utilisation dans d'autres fixtures
            $this->addReference('type_absence_' . $typeData['code'], $typeAbsence);
        }

        $manager->flush();
    }
}
