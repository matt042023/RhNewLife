<?php

namespace App\Command;

use App\Entity\Villa;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:assign-villa-colors',
    description: 'Assign predefined colors to villas for planning visualization',
)]
class AssignVillaColorsCommand extends Command
{
    private const COLORS = [
        '#10B981', // Vert (Villa A)
        '#8B5CF6', // Violet (Villa B)
    ];

    public function __construct(
        private EntityManagerInterface $em
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $villas = $this->em->getRepository(Villa::class)->findAll();

        if (empty($villas)) {
            $io->warning('No villas found in the database.');
            return Command::SUCCESS;
        }

        $io->title('Assigning colors to villas');

        $colorIndex = 0;
        $updated = 0;

        foreach ($villas as $villa) {
            // Skip if already has a color
            if ($villa->getColor() !== null) {
                $io->text(sprintf(
                    'Villa "%s" already has color %s - skipping',
                    $villa->getNom(),
                    $villa->getColor()
                ));
                continue;
            }

            // Assign color (cycle through available colors)
            $color = self::COLORS[$colorIndex % count(self::COLORS)];
            $villa->setColor($color);

            $io->success(sprintf(
                'Assigned color %s to villa "%s"',
                $color,
                $villa->getNom()
            ));

            $colorIndex++;
            $updated++;
        }

        // Flush all changes
        $this->em->flush();

        $io->success(sprintf('Successfully assigned colors to %d villas', $updated));

        return Command::SUCCESS;
    }
}
