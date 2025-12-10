<?php

namespace App\Service\Appointment;

use App\Entity\RendezVous;
use App\Entity\User;
use App\Entity\AppointmentParticipant;
use App\Repository\RendezVousRepository;
use App\Repository\AppointmentParticipantRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class AppointmentService
{
    public function __construct(
        private EntityManagerInterface $em,
        private RendezVousRepository $appointmentRepository,
        private AppointmentParticipantRepository $participantRepository,
        private AppointmentAbsenceService $absenceService,
        private AppointmentConflictService $conflictService,
        private AppointmentNotificationService $notificationService,
        private LoggerInterface $logger
    ) {}

    /**
     * UC29: Admin crée une convocation
     *
     * @param User $organizer L'organisateur (admin/director)
     * @param string $subject Objet du rendez-vous
     * @param \DateTimeInterface $scheduledAt Date et heure du RDV
     * @param int $durationMinutes Durée en minutes
     * @param array $participants Liste des utilisateurs participants
     * @param string|null $description Description complémentaire
     * @param string|null $location Lieu du rendez-vous
     * @param bool $createsAbsence Génère une absence automatique
     * @return RendezVous
     * @throws \InvalidArgumentException
     */
    public function createConvocation(
        User $organizer,
        string $subject,
        \DateTimeInterface $scheduledAt,
        int $durationMinutes,
        array $participants,
        ?string $description = null,
        ?string $location = null,
        bool $createsAbsence = false
    ): RendezVous {
        // Règle RM-01: Min 1 participant
        if (empty($participants)) {
            throw new \InvalidArgumentException('Au moins un participant est requis');
        }

        // Règle RM-02: Date future
        $this->validateFutureDate($scheduledAt);

        // Créer le rendez-vous
        $appointment = new RendezVous();
        $appointment->setType(RendezVous::TYPE_CONVOCATION);
        $appointment->setStatut(RendezVous::STATUS_CONFIRME); // Statut direct CONFIRME pour convocation
        $appointment->setOrganizer($organizer);
        $appointment->setCreatedBy($organizer);
        $appointment->setSubject($subject);
        $appointment->setStartAt($scheduledAt);
        $appointment->setDurationMinutes($durationMinutes);
        $appointment->setLocation($location);
        $appointment->setDescription($description);
        $appointment->setCreatesAbsence($createsAbsence);

        // Calculer endAt
        $endAt = clone $scheduledAt;
        $endAt->modify("+{$durationMinutes} minutes");
        $appointment->setEndAt($endAt);

        // Titre pour compatibilité
        $appointment->setTitre($subject);

        // Persister l'appointment
        $this->em->persist($appointment);

        // Ajouter les participants
        foreach ($participants as $participant) {
            if (!$participant instanceof User) {
                continue;
            }

            $appointmentParticipant = new AppointmentParticipant();
            $appointmentParticipant->setAppointment($appointment);
            $appointmentParticipant->setUser($participant);
            $appointmentParticipant->setPresenceStatus(AppointmentParticipant::PRESENCE_PENDING);

            $this->em->persist($appointmentParticipant);
            $appointment->addAppointmentParticipant($appointmentParticipant);

            // Créer l'absence si nécessaire
            if ($createsAbsence) {
                try {
                    $absence = $this->absenceService->createAbsenceForParticipant($appointmentParticipant, $appointment);
                    $appointmentParticipant->setLinkedAbsence($absence);
                } catch (\Exception $e) {
                    $this->logger->error('Erreur création absence pour participant', [
                        'participant' => $participant->getId(),
                        'appointment' => $appointment->getId(),
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }

        $this->em->flush();

        // Envoyer les notifications
        try {
            $this->notificationService->notifyConvocation($appointment);
        } catch (\Exception $e) {
            $this->logger->error('Erreur envoi notification convocation', [
                'appointment' => $appointment->getId(),
                'error' => $e->getMessage()
            ]);
        }

        $this->logger->info('Convocation créée', [
            'appointment_id' => $appointment->getId(),
            'organizer_id' => $organizer->getId(),
            'participants_count' => count($participants)
        ]);

        return $appointment;
    }

    /**
     * UC30: User demande un rendez-vous
     *
     * @param User $requester L'utilisateur qui demande le RDV
     * @param string $subject Objet de la demande
     * @param User $recipient Le destinataire (admin/director)
     * @param \DateTimeInterface|null $preferredDate Date souhaitée (optionnelle)
     * @param string|null $message Message complémentaire
     * @return RendezVous
     */
    public function createRequest(
        User $requester,
        string $subject,
        User $recipient,
        ?\DateTimeInterface $preferredDate = null,
        ?string $message = null
    ): RendezVous {
        // Créer le rendez-vous
        $appointment = new RendezVous();
        $appointment->setType(RendezVous::TYPE_DEMANDE);
        $appointment->setStatut(RendezVous::STATUS_EN_ATTENTE); // En attente de validation
        $appointment->setOrganizer($recipient); // L'organisateur est le destinataire
        $appointment->setCreatedBy($requester);
        $appointment->setSubject($subject);
        $appointment->setDescription($message);

        if ($preferredDate) {
            $appointment->setStartAt($preferredDate);
            $appointment->setEndAt($preferredDate); // Temporaire, sera défini lors de la validation
        } else {
            // Date par défaut dans 1 semaine si non spécifiée
            $defaultDate = new \DateTime('+1 week');
            $appointment->setStartAt($defaultDate);
            $appointment->setEndAt($defaultDate);
        }

        // Titre pour compatibilité
        $appointment->setTitre($subject);

        $this->em->persist($appointment);

        // Ajouter le requester comme participant
        $requesterParticipant = new AppointmentParticipant();
        $requesterParticipant->setAppointment($appointment);
        $requesterParticipant->setUser($requester);
        $requesterParticipant->setPresenceStatus(AppointmentParticipant::PRESENCE_PENDING);

        $this->em->persist($requesterParticipant);
        $appointment->addAppointmentParticipant($requesterParticipant);

        // Ajouter le recipient (destinataire) comme participant
        $recipientParticipant = new AppointmentParticipant();
        $recipientParticipant->setAppointment($appointment);
        $recipientParticipant->setUser($recipient);
        $recipientParticipant->setPresenceStatus(AppointmentParticipant::PRESENCE_PENDING);

        $this->em->persist($recipientParticipant);
        $appointment->addAppointmentParticipant($recipientParticipant);

        $this->em->flush();

        // Notifier le destinataire
        try {
            $this->notificationService->notifyNewRequest($appointment);
        } catch (\Exception $e) {
            $this->logger->error('Erreur envoi notification demande RDV', [
                'appointment' => $appointment->getId(),
                'error' => $e->getMessage()
            ]);
        }

        $this->logger->info('Demande RDV créée', [
            'appointment_id' => $appointment->getId(),
            'requester_id' => $requester->getId(),
            'recipient_id' => $recipient->getId()
        ]);

        return $appointment;
    }

    /**
     * UC31: Admin valide une demande de rendez-vous
     *
     * @param RendezVous $appointment La demande à valider
     * @param User $validator L'admin qui valide
     * @param \DateTimeInterface $scheduledAt Date/heure confirmée
     * @param int $durationMinutes Durée confirmée
     * @param string|null $location Lieu
     * @param bool $createsAbsence Génère une absence
     * @throws \InvalidArgumentException
     */
    public function validateRequest(
        RendezVous $appointment,
        User $validator,
        \DateTimeInterface $scheduledAt,
        int $durationMinutes,
        ?string $location = null,
        bool $createsAbsence = false
    ): void {
        if (!$appointment->canBeValidated()) {
            throw new \InvalidArgumentException('Cette demande ne peut pas être validée');
        }

        // Mettre à jour les détails du rendez-vous
        $appointment->setStatut(RendezVous::STATUS_CONFIRME);
        $appointment->setStartAt($scheduledAt);
        $appointment->setDurationMinutes($durationMinutes);
        $appointment->setLocation($location);
        $appointment->setCreatesAbsence($createsAbsence);

        // Calculer endAt
        $endAt = clone $scheduledAt;
        $endAt->modify("+{$durationMinutes} minutes");
        $appointment->setEndAt($endAt);

        // Créer l'absence si nécessaire
        if ($createsAbsence) {
            foreach ($appointment->getAppointmentParticipants() as $participant) {
                try {
                    $absence = $this->absenceService->createAbsenceForParticipant($participant, $appointment);
                    $participant->setLinkedAbsence($absence);
                } catch (\Exception $e) {
                    $this->logger->error('Erreur création absence lors validation', [
                        'participant' => $participant->getId(),
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }

        $this->em->flush();

        // Notifier le demandeur
        try {
            $this->notificationService->notifyRequestValidated($appointment);
        } catch (\Exception $e) {
            $this->logger->error('Erreur envoi notification validation', [
                'appointment' => $appointment->getId(),
                'error' => $e->getMessage()
            ]);
        }

        $this->logger->info('Demande RDV validée', [
            'appointment_id' => $appointment->getId(),
            'validator_id' => $validator->getId()
        ]);
    }

    /**
     * UC31: Admin refuse une demande de rendez-vous
     *
     * @param RendezVous $appointment La demande à refuser
     * @param User $validator L'admin qui refuse
     * @param string $refusalReason Motif du refus (obligatoire)
     * @throws \InvalidArgumentException
     */
    public function refuseRequest(
        RendezVous $appointment,
        User $validator,
        string $refusalReason
    ): void {
        if (!$appointment->canBeValidated()) {
            throw new \InvalidArgumentException('Cette demande ne peut pas être refusée');
        }

        if (empty(trim($refusalReason))) {
            throw new \InvalidArgumentException('Le motif de refus est obligatoire');
        }

        $appointment->setStatut(RendezVous::STATUS_REFUSE);
        $appointment->setRefusalReason($refusalReason);

        $this->em->flush();

        // Notifier le demandeur
        try {
            $this->notificationService->notifyRequestRefused($appointment);
        } catch (\Exception $e) {
            $this->logger->error('Erreur envoi notification refus', [
                'appointment' => $appointment->getId(),
                'error' => $e->getMessage()
            ]);
        }

        $this->logger->info('Demande RDV refusée', [
            'appointment_id' => $appointment->getId(),
            'validator_id' => $validator->getId()
        ]);
    }

    /**
     * UC32: Participant confirme sa présence
     *
     * @param RendezVous $appointment Le rendez-vous
     * @param User $participant Le participant qui confirme
     * @throws \InvalidArgumentException
     */
    public function confirmPresence(RendezVous $appointment, User $participant): void
    {
        $appointmentParticipant = $this->findParticipant($appointment, $participant);

        if (!$appointmentParticipant) {
            throw new \InvalidArgumentException('Participant non trouvé pour ce rendez-vous');
        }

        $appointmentParticipant->confirm();
        $this->em->flush();

        // Vérifier si tous les participants ont confirmé
        if ($appointment->getAllConfirmed()) {
            $appointment->setStatut(RendezVous::STATUS_TERMINE);
            $this->em->flush();

            $this->logger->info('Tous les participants ont confirmé, RDV terminé', [
                'appointment_id' => $appointment->getId()
            ]);
        }

        $this->logger->info('Présence confirmée', [
            'appointment_id' => $appointment->getId(),
            'participant_id' => $participant->getId()
        ]);
    }

    /**
     * UC32: Participant signale son absence
     *
     * @param RendezVous $appointment Le rendez-vous
     * @param User $participant Le participant absent
     * @throws \InvalidArgumentException
     */
    public function signalAbsence(RendezVous $appointment, User $participant): void
    {
        $appointmentParticipant = $this->findParticipant($appointment, $participant);

        if (!$appointmentParticipant) {
            throw new \InvalidArgumentException('Participant non trouvé pour ce rendez-vous');
        }

        $appointmentParticipant->markAbsent();
        $this->em->flush();

        $this->logger->info('Absence signalée', [
            'appointment_id' => $appointment->getId(),
            'participant_id' => $participant->getId()
        ]);
    }

    /**
     * Met à jour un rendez-vous
     *
     * @param RendezVous $appointment Le rendez-vous à modifier
     * @param array $data Données à mettre à jour
     */
    public function updateAppointment(RendezVous $appointment, array $data): void
    {
        if (!$appointment->canBeEdited()) {
            throw new \InvalidArgumentException('Ce rendez-vous ne peut plus être modifié');
        }

        if (isset($data['subject'])) {
            $appointment->setSubject($data['subject']);
            $appointment->setTitre($data['subject']);
        }

        if (isset($data['scheduledAt'])) {
            $appointment->setStartAt($data['scheduledAt']);
        }

        if (isset($data['durationMinutes'])) {
            $appointment->setDurationMinutes($data['durationMinutes']);

            // Recalculer endAt
            if ($appointment->getStartAt()) {
                $endAt = clone $appointment->getStartAt();
                $endAt->modify("+{$data['durationMinutes']} minutes");
                $appointment->setEndAt($endAt);
            }
        }

        if (isset($data['location'])) {
            $appointment->setLocation($data['location']);
        }

        if (isset($data['description'])) {
            $appointment->setDescription($data['description']);
        }

        $this->em->flush();

        $this->logger->info('Rendez-vous modifié', [
            'appointment_id' => $appointment->getId()
        ]);
    }

    /**
     * Annule un rendez-vous
     *
     * @param RendezVous $appointment Le rendez-vous à annuler
     * @param User $canceledBy L'utilisateur qui annule
     * @param string|null $reason Raison de l'annulation
     */
    public function cancelAppointment(
        RendezVous $appointment,
        User $canceledBy,
        ?string $reason = null
    ): void {
        $appointment->setStatut(RendezVous::STATUS_ANNULE);

        if ($reason) {
            $appointment->setDescription(($appointment->getDescription() ?? '') . "\n\nAnnulé: " . $reason);
        }

        // Supprimer les absences liées
        $this->absenceService->removeLinkedAbsences($appointment);

        $this->em->flush();

        // Notifier les participants
        try {
            $this->notificationService->notifyCancellation($appointment, $reason);
        } catch (\Exception $e) {
            $this->logger->error('Erreur envoi notification annulation', [
                'appointment' => $appointment->getId(),
                'error' => $e->getMessage()
            ]);
        }

        $this->logger->info('Rendez-vous annulé', [
            'appointment_id' => $appointment->getId(),
            'canceled_by' => $canceledBy->getId()
        ]);
    }

    /**
     * Trouve un participant dans un rendez-vous
     */
    private function findParticipant(RendezVous $appointment, User $user): ?AppointmentParticipant
    {
        foreach ($appointment->getAppointmentParticipants() as $participant) {
            if ($participant->getUser() === $user) {
                return $participant;
            }
        }
        return null;
    }

    /**
     * Règle RM-02: Valide que la date est dans le futur
     */
    private function validateFutureDate(\DateTimeInterface $date): void
    {
        $now = new \DateTime();
        if ($date <= $now) {
            throw new \InvalidArgumentException('La date du rendez-vous doit être dans le futur');
        }
    }
}
