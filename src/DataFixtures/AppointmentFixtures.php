<?php

namespace App\DataFixtures;

use App\Entity\RendezVous;
use App\Entity\AppointmentParticipant;
use App\Entity\User;
use App\Entity\TypeAbsence;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class AppointmentFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        // Récupérer des utilisateurs de test
        $admin = $this->getReference('admin', User::class);
        $director = $this->getReference('director', User::class);
        $educator1 = $this->getReference('educator-1', User::class);
        $educator2 = $this->getReference('educator-2', User::class);

        // Récupérer le type d'absence REUNION
        $reunionType = $manager->getRepository(TypeAbsence::class)
            ->findOneBy(['code' => TypeAbsence::CODE_REUNION]);

        // 1. Convocation confirmée (future)
        $convocation1 = new RendezVous();
        $convocation1->setType(RendezVous::TYPE_CONVOCATION);
        $convocation1->setStatut(RendezVous::STATUS_CONFIRME);
        $convocation1->setOrganizer($admin);
        $convocation1->setCreatedBy($admin);
        $convocation1->setSubject('Réunion d\'équipe mensuelle');
        $convocation1->setStartAt(new \DateTime('+5 days 10:00'));
        $convocation1->setDurationMinutes(90);
        $convocation1->setLocation('Salle de réunion A');
        $convocation1->setDescription('Ordre du jour :
- Bilan du mois
- Objectifs du mois prochain
- Questions diverses');
        $convocation1->setCreatesAbsence(true);

        $endAt1 = clone $convocation1->getStartAt();
        $endAt1->modify('+90 minutes');
        $convocation1->setEndAt($endAt1);
        $convocation1->setTitre($convocation1->getSubject());

        $manager->persist($convocation1);

        // Ajouter les participants
        $participant1 = new AppointmentParticipant();
        $participant1->setAppointment($convocation1);
        $participant1->setUser($educator1);
        $participant1->setPresenceStatus(AppointmentParticipant::PRESENCE_CONFIRMED);
        $participant1->confirm();
        $manager->persist($participant1);

        $participant2 = new AppointmentParticipant();
        $participant2->setAppointment($convocation1);
        $participant2->setUser($educator2);
        $participant2->setPresenceStatus(AppointmentParticipant::PRESENCE_PENDING);
        $manager->persist($participant2);

        $participant3 = new AppointmentParticipant();
        $participant3->setAppointment($convocation1);
        $participant3->setUser($director);
        $participant3->setPresenceStatus(AppointmentParticipant::PRESENCE_CONFIRMED);
        $participant3->confirm();
        $manager->persist($participant3);

        // 2. Demande en attente
        $request1 = new RendezVous();
        $request1->setType(RendezVous::TYPE_DEMANDE);
        $request1->setStatut(RendezVous::STATUS_EN_ATTENTE);
        $request1->setOrganizer($admin); // Le destinataire
        $request1->setCreatedBy($educator1); // Le demandeur
        $request1->setSubject('Entretien individuel - Point de situation');
        $request1->setStartAt(new \DateTime('+10 days'));
        $request1->setEndAt(new \DateTime('+10 days'));
        $request1->setDescription('Je souhaiterais faire un point sur ma situation et discuter de mes perspectives d\'évolution.');
        $request1->setTitre($request1->getSubject());

        $manager->persist($request1);

        $participant4 = new AppointmentParticipant();
        $participant4->setAppointment($request1);
        $participant4->setUser($educator1);
        $participant4->setPresenceStatus(AppointmentParticipant::PRESENCE_PENDING);
        $manager->persist($participant4);

        // 3. Convocation passée et terminée
        $convocation2 = new RendezVous();
        $convocation2->setType(RendezVous::TYPE_CONVOCATION);
        $convocation2->setStatut(RendezVous::STATUS_TERMINE);
        $convocation2->setOrganizer($director);
        $convocation2->setCreatedBy($director);
        $convocation2->setSubject('Formation sécurité incendie');
        $convocation2->setStartAt(new \DateTime('-3 days 14:00'));
        $convocation2->setDurationMinutes(120);
        $convocation2->setLocation('Salle de formation');
        $convocation2->setCreatesAbsence(true);

        $endAt2 = clone $convocation2->getStartAt();
        $endAt2->modify('+120 minutes');
        $convocation2->setEndAt($endAt2);
        $convocation2->setTitre($convocation2->getSubject());

        $manager->persist($convocation2);

        $participant5 = new AppointmentParticipant();
        $participant5->setAppointment($convocation2);
        $participant5->setUser($educator1);
        $participant5->setPresenceStatus(AppointmentParticipant::PRESENCE_CONFIRMED);
        $participant5->confirm();
        $manager->persist($participant5);

        $participant6 = new AppointmentParticipant();
        $participant6->setAppointment($convocation2);
        $participant6->setUser($educator2);
        $participant6->setPresenceStatus(AppointmentParticipant::PRESENCE_CONFIRMED);
        $participant6->confirm();
        $manager->persist($participant6);

        // 4. Demande refusée
        $request2 = new RendezVous();
        $request2->setType(RendezVous::TYPE_DEMANDE);
        $request2->setStatut(RendezVous::STATUS_REFUSE);
        $request2->setOrganizer($admin);
        $request2->setCreatedBy($educator2);
        $request2->setSubject('Demande de congé exceptionnel');
        $request2->setStartAt(new \DateTime('+2 days'));
        $request2->setEndAt(new \DateTime('+2 days'));
        $request2->setDescription('Je souhaiterais un entretien pour discuter d\'une demande de congé exceptionnel.');
        $request2->setRefusalReason('Période de forte activité, merci de reporter votre demande au mois prochain.');
        $request2->setTitre($request2->getSubject());

        $manager->persist($request2);

        $participant7 = new AppointmentParticipant();
        $participant7->setAppointment($request2);
        $participant7->setUser($educator2);
        $participant7->setPresenceStatus(AppointmentParticipant::PRESENCE_PENDING);
        $manager->persist($participant7);

        // 5. Convocation annulée
        $convocation3 = new RendezVous();
        $convocation3->setType(RendezVous::TYPE_CONVOCATION);
        $convocation3->setStatut(RendezVous::STATUS_ANNULE);
        $convocation3->setOrganizer($admin);
        $convocation3->setCreatedBy($admin);
        $convocation3->setSubject('Réunion budget - ANNULÉE');
        $convocation3->setStartAt(new \DateTime('+7 days 09:00'));
        $convocation3->setDurationMinutes(60);
        $convocation3->setLocation('Salle de réunion B');
        $convocation3->setDescription('Réunion annulée : reporter au mois prochain.');

        $endAt3 = clone $convocation3->getStartAt();
        $endAt3->modify('+60 minutes');
        $convocation3->setEndAt($endAt3);
        $convocation3->setTitre($convocation3->getSubject());

        $manager->persist($convocation3);

        $participant8 = new AppointmentParticipant();
        $participant8->setAppointment($convocation3);
        $participant8->setUser($director);
        $participant8->setPresenceStatus(AppointmentParticipant::PRESENCE_PENDING);
        $manager->persist($participant8);

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            AppFixtures::class,          // Pour récupérer admin/director
            EducatorFixtures::class,     // Pour récupérer les éducateurs
        ];
    }
}
