<?php

namespace App\DataFixtures;

use App\Entity\AppointmentParticipant;
use App\Entity\RendezVous;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class EducatorAppointmentFixtures extends Fixture implements DependentFixtureInterface
{

    public function load(ObjectManager $manager): void
    {
        // Seed fixe pour reproductibilité
        mt_srand(12345);

        // Récupérer admin et director
        /** @var User $admin */
        $admin = $this->getReference('admin', User::class);
        /** @var User $director */
        $director = $this->getReference('director', User::class);

        $totalAppointments = 0;

        // A. Rendez-vous personnels (2 par éducateur = 12 total)
        for ($i = 1; $i <= 6; $i++) {
            /** @var User $educator */
            $educator = $this->getReference("educator-{$i}", User::class);

            // 1. Entretien individuel passé (TERMINE)
            $totalAppointments += $this->createPastPersonalAppointment($manager, $educator, $admin);

            // 2. Demande future validée (CONFIRME)
            $totalAppointments += $this->createFuturePersonalAppointment($manager, $educator, $admin, $i);
        }

        // B. Rendez-vous de groupe (4 au total)

        // 1. Réunion d'équipe mensuelle (passée, TERMINE)
        $totalAppointments += $this->createTeamMeeting($manager, $admin);

        // 2. Formation collective (future, CONFIRME)
        $totalAppointments += $this->createTrainingSession($manager, $director);

        // 3. Réunion de supervision (future, CONFIRME)
        $totalAppointments += $this->createSupervisionMeeting($manager, $admin);

        // 4. Réunion annulée (ANNULE)
        $totalAppointments += $this->createCancelledMeeting($manager, $admin);

        // C. Demandes en attente/refusées (2 au total)

        // 1. Demande non traitée (EN_ATTENTE)
        $totalAppointments += $this->createPendingRequest($manager);

        // 2. Demande refusée (REFUSE)
        $totalAppointments += $this->createRefusedRequest($manager);

        $manager->flush();

        echo "\n✅ {$totalAppointments} rendez-vous créés avec succès !\n\n";
    }

    /**
     * Crée un entretien individuel passé (TERMINE)
     */
    private function createPastPersonalAppointment(ObjectManager $manager, User $educator, User $admin): int
    {
        $daysAgo = mt_rand(60, 90); // 2-3 mois
        $startDate = new \DateTime("-{$daysAgo} days");
        $startDate->setTime(10, 0);

        $appointment = new RendezVous();
        $appointment->setType(RendezVous::TYPE_DEMANDE);
        $appointment->setStatut(RendezVous::STATUS_TERMINE);
        $appointment->setOrganizer($admin);
        $appointment->setCreatedBy($educator);
        $appointment->setSubject('Entretien annuel d\'évaluation');
        $appointment->setTitre('Entretien annuel d\'évaluation'); // Compatibilité
        $appointment->setStartAt($startDate);
        $appointment->setDurationMinutes(60);
        $appointment->setLocation('Bureau administratif');
        $appointment->setDescription('Bilan de l\'année et perspectives d\'évolution');

        // Calculer endAt
        $endDate = clone $startDate;
        $endDate->modify('+60 minutes');
        $appointment->setEndAt($endDate);

        $manager->persist($appointment);

        // Ajouter les participants (educator et admin) - Les 2 ont confirmé leur présence
        $this->addParticipant($manager, $appointment, $educator, AppointmentParticipant::PRESENCE_CONFIRMED);
        $this->addParticipant($manager, $appointment, $admin, AppointmentParticipant::PRESENCE_CONFIRMED);

        return 1;
    }

    /**
     * Crée une demande future validée (CONFIRME)
     */
    private function createFuturePersonalAppointment(ObjectManager $manager, User $educator, User $admin, int $educatorNum): int
    {
        $daysAhead = mt_rand(14, 28); // 2-4 semaines
        $startDate = new \DateTime("+{$daysAhead} days");
        $startDate->setTime(14, 0);

        $subjects = [
            'Demande de congés exceptionnels',
            'Point sur situation personnelle',
            'Demande de formation continue',
            'Évolution de carrière',
            'Demande de mobilité interne',
            'Entretien de mi-parcours',
        ];

        $appointment = new RendezVous();
        $appointment->setType(RendezVous::TYPE_DEMANDE);
        $appointment->setStatut(RendezVous::STATUS_CONFIRME);
        $appointment->setOrganizer($admin);
        $appointment->setCreatedBy($educator);
        $subject = $subjects[($educatorNum - 1) % count($subjects)];
        $appointment->setSubject($subject);
        $appointment->setTitre($subject); // Compatibilité
        $appointment->setStartAt($startDate);
        $appointment->setDurationMinutes(45);
        $appointment->setLocation('Salle de réunion B');
        $appointment->setDescription('Rendez-vous individuel sur demande de ' . $educator->getFullName());
        $appointment->setImpactGarde(true); // Impact sur les gardes pour affichage calendrier

        // Calculer endAt
        $endDate = clone $startDate;
        $endDate->modify('+45 minutes');
        $appointment->setEndAt($endDate);

        $manager->persist($appointment);

        // Ajouter les participants - En attente de confirmation
        $this->addParticipant($manager, $appointment, $educator, AppointmentParticipant::PRESENCE_PENDING);
        $this->addParticipant($manager, $appointment, $admin, AppointmentParticipant::PRESENCE_PENDING);

        return 1;
    }

    /**
     * Crée une réunion d'équipe mensuelle (passée, TERMINE)
     */
    private function createTeamMeeting(ObjectManager $manager, User $admin): int
    {
        $startDate = new \DateTime('-14 days'); // Il y a 2 semaines
        $startDate->setTime(9, 0);

        $appointment = new RendezVous();
        $appointment->setType(RendezVous::TYPE_CONVOCATION);
        $appointment->setStatut(RendezVous::STATUS_TERMINE);
        $appointment->setOrganizer($admin);
        $appointment->setCreatedBy($admin);
        $appointment->setSubject('Réunion d\'équipe - Novembre 2024');
        $appointment->setTitre('Réunion d\'équipe - Novembre 2024');
        $appointment->setStartAt($startDate);
        $appointment->setDurationMinutes(90);
        $appointment->setLocation('Salle de réunion principale');
        $appointment->setDescription('Ordre du jour: Bilan du mois, projets en cours, points d\'amélioration');

        // Calculer endAt
        $endDate = clone $startDate;
        $endDate->modify('+90 minutes');
        $appointment->setEndAt($endDate);

        $manager->persist($appointment);

        // Ajouter les 6 éducateurs (5 confirmés, 1 absent)
        for ($i = 1; $i <= 6; $i++) {
            /** @var User $educator */
            $educator = $this->getReference("educator-{$i}", User::class);
            $presenceStatus = ($i === 3) ? AppointmentParticipant::PRESENCE_ABSENT : AppointmentParticipant::PRESENCE_CONFIRMED;

            $this->addParticipant($manager, $appointment, $educator, $presenceStatus);
        }

        return 1;
    }

    /**
     * Crée une formation collective (future, CONFIRME)
     */
    private function createTrainingSession(ObjectManager $manager, User $director): int
    {
        $startDate = new \DateTime('+21 days'); // Dans 3 semaines
        $startDate->setTime(9, 0);

        $appointment = new RendezVous();
        $appointment->setType(RendezVous::TYPE_CONVOCATION);
        $appointment->setStatut(RendezVous::STATUS_CONFIRME);
        $appointment->setOrganizer($director);
        $appointment->setCreatedBy($director);
        $appointment->setSubject('Formation : Gestion des situations de crise');
        $appointment->setTitre('Formation : Gestion des situations de crise');
        $appointment->setStartAt($startDate);
        $appointment->setDurationMinutes(240); // Demi-journée
        $appointment->setLocation('Centre de formation externe');
        $appointment->setDescription('Formation animée par un formateur spécialisé. Présence obligatoire.');
        $appointment->setImpactGarde(true); // Impact sur les gardes

        // Calculer endAt
        $endDate = clone $startDate;
        $endDate->modify('+240 minutes');
        $appointment->setEndAt($endDate);

        $manager->persist($appointment);

        // Ajouter 4 éducateurs aléatoires
        $selectedEducators = [1, 2, 4, 6];
        foreach ($selectedEducators as $num) {
            /** @var User $educator */
            $educator = $this->getReference("educator-{$num}", User::class);
            $this->addParticipant($manager, $appointment, $educator, AppointmentParticipant::PRESENCE_PENDING);
        }

        return 1;
    }

    /**
     * Crée une réunion de supervision (future, CONFIRME)
     */
    private function createSupervisionMeeting(ObjectManager $manager, User $admin): int
    {
        $startDate = new \DateTime('+10 days'); // Dans 10 jours
        $startDate->setTime(14, 0);

        $appointment = new RendezVous();
        $appointment->setType(RendezVous::TYPE_CONVOCATION);
        $appointment->setStatut(RendezVous::STATUS_CONFIRME);
        $appointment->setOrganizer($admin);
        $appointment->setCreatedBy($admin);
        $appointment->setSubject('Supervision d\'équipe - Cas cliniques');
        $appointment->setTitre('Supervision d\'équipe - Cas cliniques');
        $appointment->setStartAt($startDate);
        $appointment->setDurationMinutes(120);
        $appointment->setLocation('Villa des Roses - Salle polyvalente');
        $appointment->setDescription('Analyse de situations cliniques et partage de pratiques. Équipe Villa des Roses.');
        $appointment->setImpactGarde(true); // Impact sur les gardes

        // Calculer endAt
        $endDate = clone $startDate;
        $endDate->modify('+120 minutes');
        $appointment->setEndAt($endDate);

        $manager->persist($appointment);

        // Ajouter 3 éducateurs de la Villa des Roses (1 et 3)
        $selectedEducators = [1, 3];
        foreach ($selectedEducators as $num) {
            /** @var User $educator */
            $educator = $this->getReference("educator-{$num}", User::class);
            $this->addParticipant($manager, $appointment, $educator, AppointmentParticipant::PRESENCE_PENDING);
        }

        return 1;
    }

    /**
     * Crée une réunion annulée (ANNULE)
     */
    private function createCancelledMeeting(ObjectManager $manager, User $admin): int
    {
        $startDate = new \DateTime('-7 days'); // Il y a 1 semaine
        $startDate->setTime(10, 0);

        $appointment = new RendezVous();
        $appointment->setType(RendezVous::TYPE_CONVOCATION);
        $appointment->setStatut(RendezVous::STATUS_ANNULE);
        $appointment->setOrganizer($admin);
        $appointment->setCreatedBy($admin);
        $appointment->setSubject('Présentation nouveau projet');
        $appointment->setTitre('Présentation nouveau projet');
        $appointment->setStartAt($startDate);
        $appointment->setDurationMinutes(60);
        $appointment->setLocation('Salle de réunion A');
        $appointment->setDescription('Présentation du nouveau projet éducatif.\n\nAnnulé: Report pour raisons techniques');

        // Calculer endAt
        $endDate = clone $startDate;
        $endDate->modify('+60 minutes');
        $appointment->setEndAt($endDate);

        $manager->persist($appointment);

        // Ajouter les 6 éducateurs (tous en PENDING car annulé avant confirmation)
        for ($i = 1; $i <= 6; $i++) {
            /** @var User $educator */
            $educator = $this->getReference("educator-{$i}", User::class);
            $this->addParticipant($manager, $appointment, $educator, AppointmentParticipant::PRESENCE_PENDING);
        }

        return 1;
    }

    /**
     * Crée une demande non traitée (EN_ATTENTE)
     */
    private function createPendingRequest(ObjectManager $manager): int
    {
        /** @var User $educator3 */
        $educator3 = $this->getReference('educator-3', User::class);
        /** @var User $admin */
        $admin = $this->getReference('admin', User::class);

        $startDate = new \DateTime('+7 days'); // Dans 1 semaine
        $startDate->setTime(15, 0);

        $appointment = new RendezVous();
        $appointment->setType(RendezVous::TYPE_DEMANDE);
        $appointment->setStatut(RendezVous::STATUS_EN_ATTENTE);
        $appointment->setOrganizer($admin);
        $appointment->setCreatedBy($educator3);
        $appointment->setSubject('Entretien de mi-parcours');
        $appointment->setTitre('Entretien de mi-parcours');
        $appointment->setStartAt($startDate);
        $appointment->setDurationMinutes(45);
        $appointment->setDescription('Je souhaiterais faire le point sur mon évolution professionnelle et discuter des perspectives de formation.');
        $appointment->setImpactGarde(true); // Impact sur les gardes

        // Calculer endAt
        $endDate = clone $startDate;
        $endDate->modify('+45 minutes');
        $appointment->setEndAt($endDate);

        $manager->persist($appointment);

        // Ajouter les participants
        $this->addParticipant($manager, $appointment, $educator3, AppointmentParticipant::PRESENCE_PENDING);
        $this->addParticipant($manager, $appointment, $admin, AppointmentParticipant::PRESENCE_PENDING);

        return 1;
    }

    /**
     * Crée une demande refusée (REFUSE)
     */
    private function createRefusedRequest(ObjectManager $manager): int
    {
        /** @var User $educator5 */
        $educator5 = $this->getReference('educator-5', User::class);
        /** @var User $director */
        $director = $this->getReference('director', User::class);

        $startDate = new \DateTime('-1 day'); // Hier
        $startDate->setTime(11, 0);

        $appointment = new RendezVous();
        $appointment->setType(RendezVous::TYPE_DEMANDE);
        $appointment->setStatut(RendezVous::STATUS_REFUSE);
        $appointment->setOrganizer($director);
        $appointment->setCreatedBy($educator5);
        $appointment->setSubject('Demande de changement d\'équipe');
        $appointment->setTitre('Demande de changement d\'équipe');
        $appointment->setStartAt($startDate);
        $appointment->setDurationMinutes(30);
        $appointment->setDescription('Je souhaite discuter d\'une possible mobilité vers une autre structure.');
        $appointment->setRefusalReason('Cette demande nécessite une réunion collégiale. Merci de renouveler votre demande après la prochaine réunion d\'équipe.');

        // Calculer endAt
        $endDate = clone $startDate;
        $endDate->modify('+30 minutes');
        $appointment->setEndAt($endDate);

        $manager->persist($appointment);

        // Ajouter les participants
        $this->addParticipant($manager, $appointment, $educator5, AppointmentParticipant::PRESENCE_PENDING);
        $this->addParticipant($manager, $appointment, $director, AppointmentParticipant::PRESENCE_PENDING);

        return 1;
    }

    /**
     * Ajoute un participant à un rendez-vous
     */
    private function addParticipant(
        ObjectManager $manager,
        RendezVous $appointment,
        User $user,
        string $presenceStatus
    ): AppointmentParticipant {
        $participant = new AppointmentParticipant();
        $participant->setAppointment($appointment);
        $participant->setUser($user);
        $participant->setPresenceStatus($presenceStatus);

        if ($presenceStatus === AppointmentParticipant::PRESENCE_CONFIRMED) {
            $participant->setConfirmedAt(new \DateTime('-3 days'));
        }

        $manager->persist($participant);
        $appointment->addAppointmentParticipant($participant);

        return $participant;
    }

    public function getDependencies(): array
    {
        return [
            EducatorFixtures::class,
            AppFixtures::class,
        ];
    }
}
