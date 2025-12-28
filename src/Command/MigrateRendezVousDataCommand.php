<?php

namespace App\Command;

use App\Entity\RendezVous;
use App\Entity\AppointmentParticipant;
use App\Repository\RendezVousRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:migrate-rendezvous-data',
    description: 'Migre les données de rendez-vous vers la nouvelle structure',
)]
class MigrateRendezVousDataCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private RendezVousRepository $rendezVousRepository
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Exécuter en mode simulation sans modification de la base de données')
            ->setHelp('Cette commande migre les anciennes données de rendez-vous vers la nouvelle structure avec AppointmentParticipant');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');

        if ($dryRun) {
            $io->warning('Mode DRY-RUN activé - Aucune modification ne sera enregistrée');
        }

        $io->title('Migration des données Rendez-vous');

        // Statistiques
        $stats = [
            'total' => 0,
            'migrated' => 0,
            'participants_created' => 0,
            'errors' => 0,
            'already_migrated' => 0
        ];

        try {
            // Récupérer tous les rendez-vous
            $appointments = $this->rendezVousRepository->findAll();
            $stats['total'] = count($appointments);

            $io->section(sprintf('Trouvé %d rendez-vous à vérifier', $stats['total']));

            $progressBar = $io->createProgressBar($stats['total']);
            $progressBar->start();

            foreach ($appointments as $appointment) {
                try {
                    // Vérifier si déjà migré (a des AppointmentParticipant)
                    if ($appointment->getAppointmentParticipants()->count() > 0) {
                        $stats['already_migrated']++;
                        $progressBar->advance();
                        continue;
                    }

                    // Récupérer les participants de l'ancienne relation ManyToMany
                    $oldParticipants = $appointment->getParticipants();

                    if ($oldParticipants->count() === 0) {
                        $io->warning(sprintf('RDV #%d n\'a aucun participant', $appointment->getId()));
                        $progressBar->advance();
                        continue;
                    }

                    // Créer AppointmentParticipant pour chaque participant
                    foreach ($oldParticipants as $participant) {
                        $appointmentParticipant = new AppointmentParticipant();
                        $appointmentParticipant->setAppointment($appointment);
                        $appointmentParticipant->setUser($participant);
                        $appointmentParticipant->setPresenceStatus(AppointmentParticipant::PRESENCE_PENDING);

                        if (!$dryRun) {
                            $this->em->persist($appointmentParticipant);
                        }

                        $stats['participants_created']++;
                    }

                    $stats['migrated']++;

                    if (!$dryRun && $stats['migrated'] % 20 === 0) {
                        $this->em->flush();
                    }
                } catch (\Exception $e) {
                    $stats['errors']++;
                    $io->error(sprintf(
                        'Erreur lors de la migration du RDV #%d: %s',
                        $appointment->getId(),
                        $e->getMessage()
                    ));
                }

                $progressBar->advance();
            }

            $progressBar->finish();
            $io->newLine(2);

            if (!$dryRun) {
                $this->em->flush();
                $io->success('Migration terminée avec succès');
            } else {
                $io->success('Simulation terminée (aucune modification enregistrée)');
            }

            // Afficher les statistiques
            $io->section('Statistiques de migration');
            $io->table(
                ['Métrique', 'Valeur'],
                [
                    ['Total rendez-vous', $stats['total']],
                    ['Déjà migrés', $stats['already_migrated']],
                    ['Migrés maintenant', $stats['migrated']],
                    ['Participants créés', $stats['participants_created']],
                    ['Erreurs', $stats['errors']]
                ]
            );

            if ($stats['errors'] > 0) {
                $io->warning(sprintf('%d erreur(s) rencontrée(s) pendant la migration', $stats['errors']));
                return Command::FAILURE;
            }

            if ($dryRun && $stats['migrated'] > 0) {
                $io->note('Exécutez la commande sans --dry-run pour effectuer la migration réelle');
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Erreur fatale: ' . $e->getMessage());
            $io->error($e->getTraceAsString());
            return Command::FAILURE;
        }
    }
}
