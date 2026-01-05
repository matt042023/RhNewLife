<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:assign-user-colors',
    description: 'Assign predefined colors to users for planning visualization',
)]
class AssignUserColorsCommand extends Command
{
    private const COLORS = [
        '#3B82F6', // Bleu
        '#10B981', // Vert
        '#F59E0B', // Orange
        '#8B5CF6', // Violet
        '#EC4899', // Rose
        '#14B8A6', // Teal
    ];

    public function __construct(
        private EntityManagerInterface $em
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $users = $this->em->getRepository(User::class)->findAll();

        if (empty($users)) {
            $io->warning('No users found in the database.');
            return Command::SUCCESS;
        }

        $io->title('Assigning colors to users');

        $colorIndex = 0;
        $updated = 0;

        foreach ($users as $user) {
            // Skip if already has a color
            if ($user->getColor() !== null) {
                $io->text(sprintf(
                    'User %s already has color %s - skipping',
                    $user->getEmail(),
                    $user->getColor()
                ));
                continue;
            }

            // Assign color (cycle through available colors)
            $color = self::COLORS[$colorIndex % count(self::COLORS)];
            $user->setColor($color);

            $io->success(sprintf(
                'Assigned color %s to user %s (%s %s)',
                $color,
                $user->getEmail(),
                $user->getFirstName(),
                $user->getLastName()
            ));

            $colorIndex++;
            $updated++;
        }

        // Flush all changes
        $this->em->flush();

        $io->success(sprintf('Successfully assigned colors to %d users', $updated));

        return Command::SUCCESS;
    }
}
