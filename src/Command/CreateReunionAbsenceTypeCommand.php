<?php

namespace App\Command;

use App\Entity\TypeAbsence;
use App\Repository\TypeAbsenceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:create-reunion-type',
    description: 'Crée le type d\'absence REUNION pour les rendez-vous avec absence automatique'
)]
class CreateReunionAbsenceTypeCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private TypeAbsenceRepository $typeAbsenceRepository
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Vérifier si le type existe déjà
        $existingType = $this->typeAbsenceRepository->findOneBy([
            'code' => TypeAbsence::CODE_REUNION
        ]);

        if ($existingType) {
            $io->warning('Le type d\'absence REUNION existe déjà.');
            $io->info('ID: ' . $existingType->getId());
            $io->info('Label: ' . $existingType->getLabel());
            $io->info('Code: ' . $existingType->getCode());
            $io->info('Affecte planning: ' . ($existingType->isAffectsPlanning() ? 'Oui' : 'Non'));
            return Command::SUCCESS;
        }

        // Créer le type d'absence REUNION
        $typeAbsence = new TypeAbsence();
        $typeAbsence->setLabel('Réunion');
        $typeAbsence->setCode(TypeAbsence::CODE_REUNION);
        $typeAbsence->setAffectsPlanning(true);
        $typeAbsence->setRequiresJustification(false);
        $typeAbsence->setActive(true);

        $this->em->persist($typeAbsence);
        $this->em->flush();

        $io->success('Type d\'absence REUNION créé avec succès !');
        $io->info('ID: ' . $typeAbsence->getId());
        $io->info('Label: ' . $typeAbsence->getLabel());
        $io->info('Code: ' . $typeAbsence->getCode());

        return Command::SUCCESS;
    }
}
