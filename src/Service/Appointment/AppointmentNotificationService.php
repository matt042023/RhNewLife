<?php

namespace App\Service\Appointment;

use App\Entity\RendezVous;
use App\Entity\User;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Psr\Log\LoggerInterface;

class AppointmentNotificationService
{
    public function __construct(
        private MailerInterface $mailer,
        private LoggerInterface $logger,
        private string $fromEmail = 'noreply@rhnewlife.com',
        private string $fromName = 'GestionRH NewLife'
    ) {}

    /**
     * Notifie les participants d'une nouvelle convocation
     *
     * @param RendezVous $appointment
     */
    public function notifyConvocation(RendezVous $appointment): void
    {
        foreach ($appointment->getAppointmentParticipants() as $participant) {
            $user = $participant->getUser();

            if (!$user->getEmail()) {
                $this->logger->warning('Impossible d\'envoyer notification convocation, email manquant', [
                    'user_id' => $user->getId(),
                    'appointment_id' => $appointment->getId()
                ]);
                continue;
            }

            try {
                $email = (new TemplatedEmail())
                    ->from(new Address($this->fromEmail, $this->fromName))
                    ->to(new Address($user->getEmail(), $user->getFullName()))
                    ->subject('Convocation : ' . $appointment->getSubject())
                    ->htmlTemplate('emails/appointment_convocation.html.twig')
                    ->context([
                        'appointment' => $appointment,
                        'participant' => $participant,
                        'user' => $user,
                        'organizer' => $appointment->getOrganizer(),
                    ]);

                $this->mailer->send($email);

                $this->logger->info('Notification convocation envoyée', [
                    'user_id' => $user->getId(),
                    'appointment_id' => $appointment->getId()
                ]);
            } catch (\Exception $e) {
                $this->logger->error('Erreur envoi notification convocation', [
                    'user_id' => $user->getId(),
                    'appointment_id' => $appointment->getId(),
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    /**
     * Notifie l'admin/director d'une nouvelle demande de RDV
     *
     * @param RendezVous $appointment
     */
    public function notifyNewRequest(RendezVous $appointment): void
    {
        $recipient = $appointment->getOrganizer(); // Le destinataire de la demande
        $requester = $appointment->getCreatedBy(); // Le demandeur

        if (!$recipient->getEmail()) {
            $this->logger->warning('Impossible d\'envoyer notification demande, email manquant', [
                'recipient_id' => $recipient->getId(),
                'appointment_id' => $appointment->getId()
            ]);
            return;
        }

        try {
            $email = (new TemplatedEmail())
                ->from(new Address($this->fromEmail, $this->fromName))
                ->to(new Address($recipient->getEmail(), $recipient->getFullName()))
                ->subject('Nouvelle demande de rendez-vous de ' . $requester->getFullName())
                ->htmlTemplate('emails/appointment_request_received.html.twig')
                ->context([
                    'appointment' => $appointment,
                    'requester' => $requester,
                    'recipient' => $recipient,
                ]);

            $this->mailer->send($email);

            $this->logger->info('Notification nouvelle demande envoyée', [
                'recipient_id' => $recipient->getId(),
                'requester_id' => $requester->getId(),
                'appointment_id' => $appointment->getId()
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Erreur envoi notification nouvelle demande', [
                'recipient_id' => $recipient->getId(),
                'appointment_id' => $appointment->getId(),
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Notifie le demandeur que sa demande a été validée
     *
     * @param RendezVous $appointment
     */
    public function notifyRequestValidated(RendezVous $appointment): void
    {
        $requester = $appointment->getCreatedBy();

        if (!$requester->getEmail()) {
            $this->logger->warning('Impossible d\'envoyer notification validation, email manquant', [
                'requester_id' => $requester->getId(),
                'appointment_id' => $appointment->getId()
            ]);
            return;
        }

        try {
            $email = (new TemplatedEmail())
                ->from(new Address($this->fromEmail, $this->fromName))
                ->to(new Address($requester->getEmail(), $requester->getFullName()))
                ->subject('Votre demande de rendez-vous a été acceptée')
                ->htmlTemplate('emails/appointment_validated.html.twig')
                ->context([
                    'appointment' => $appointment,
                    'requester' => $requester,
                    'organizer' => $appointment->getOrganizer(),
                ]);

            $this->mailer->send($email);

            $this->logger->info('Notification validation envoyée', [
                'requester_id' => $requester->getId(),
                'appointment_id' => $appointment->getId()
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Erreur envoi notification validation', [
                'requester_id' => $requester->getId(),
                'appointment_id' => $appointment->getId(),
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Notifie le demandeur que sa demande a été refusée
     *
     * @param RendezVous $appointment
     */
    public function notifyRequestRefused(RendezVous $appointment): void
    {
        $requester = $appointment->getCreatedBy();

        if (!$requester->getEmail()) {
            $this->logger->warning('Impossible d\'envoyer notification refus, email manquant', [
                'requester_id' => $requester->getId(),
                'appointment_id' => $appointment->getId()
            ]);
            return;
        }

        try {
            $email = (new TemplatedEmail())
                ->from(new Address($this->fromEmail, $this->fromName))
                ->to(new Address($requester->getEmail(), $requester->getFullName()))
                ->subject('Votre demande de rendez-vous a été refusée')
                ->htmlTemplate('emails/appointment_rejected.html.twig')
                ->context([
                    'appointment' => $appointment,
                    'requester' => $requester,
                    'organizer' => $appointment->getOrganizer(),
                    'refusalReason' => $appointment->getRefusalReason(),
                ]);

            $this->mailer->send($email);

            $this->logger->info('Notification refus envoyée', [
                'requester_id' => $requester->getId(),
                'appointment_id' => $appointment->getId()
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Erreur envoi notification refus', [
                'requester_id' => $requester->getId(),
                'appointment_id' => $appointment->getId(),
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Notifie les participants de l'annulation du rendez-vous
     *
     * @param RendezVous $appointment
     * @param string|null $reason Raison de l'annulation
     */
    public function notifyCancellation(RendezVous $appointment, ?string $reason = null): void
    {
        foreach ($appointment->getAppointmentParticipants() as $participant) {
            $user = $participant->getUser();

            if (!$user->getEmail()) {
                $this->logger->warning('Impossible d\'envoyer notification annulation, email manquant', [
                    'user_id' => $user->getId(),
                    'appointment_id' => $appointment->getId()
                ]);
                continue;
            }

            try {
                $email = (new TemplatedEmail())
                    ->from(new Address($this->fromEmail, $this->fromName))
                    ->to(new Address($user->getEmail(), $user->getFullName()))
                    ->subject('Rendez-vous annulé : ' . $appointment->getSubject())
                    ->htmlTemplate('emails/appointment_cancelled.html.twig')
                    ->context([
                        'appointment' => $appointment,
                        'participant' => $participant,
                        'user' => $user,
                        'organizer' => $appointment->getOrganizer(),
                        'reason' => $reason,
                    ]);

                $this->mailer->send($email);

                $this->logger->info('Notification annulation envoyée', [
                    'user_id' => $user->getId(),
                    'appointment_id' => $appointment->getId()
                ]);
            } catch (\Exception $e) {
                $this->logger->error('Erreur envoi notification annulation', [
                    'user_id' => $user->getId(),
                    'appointment_id' => $appointment->getId(),
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    /**
     * Envoie un rappel 24h avant le rendez-vous
     *
     * @param RendezVous $appointment
     */
    public function sendReminder(RendezVous $appointment): void
    {
        // Vérifier que le RDV est confirmé et dans le futur
        if ($appointment->getStatut() !== RendezVous::STATUS_CONFIRME) {
            $this->logger->debug('Rappel non envoyé, RDV non confirmé', [
                'appointment_id' => $appointment->getId(),
                'status' => $appointment->getStatut()
            ]);
            return;
        }

        $now = new \DateTime();
        $appointmentDate = $appointment->getStartAt();

        if ($appointmentDate <= $now) {
            $this->logger->debug('Rappel non envoyé, RDV dans le passé', [
                'appointment_id' => $appointment->getId()
            ]);
            return;
        }

        foreach ($appointment->getAppointmentParticipants() as $participant) {
            $user = $participant->getUser();

            if (!$user->getEmail()) {
                $this->logger->warning('Impossible d\'envoyer rappel, email manquant', [
                    'user_id' => $user->getId(),
                    'appointment_id' => $appointment->getId()
                ]);
                continue;
            }

            try {
                $email = (new TemplatedEmail())
                    ->from(new Address($this->fromEmail, $this->fromName))
                    ->to(new Address($user->getEmail(), $user->getFullName()))
                    ->subject('Rappel : Rendez-vous demain - ' . $appointment->getSubject())
                    ->htmlTemplate('emails/appointment_reminder.html.twig')
                    ->context([
                        'appointment' => $appointment,
                        'participant' => $participant,
                        'user' => $user,
                        'organizer' => $appointment->getOrganizer(),
                    ]);

                $this->mailer->send($email);

                $this->logger->info('Rappel envoyé', [
                    'user_id' => $user->getId(),
                    'appointment_id' => $appointment->getId()
                ]);
            } catch (\Exception $e) {
                $this->logger->error('Erreur envoi rappel', [
                    'user_id' => $user->getId(),
                    'appointment_id' => $appointment->getId(),
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    /**
     * Notifie l'organisateur qu'un participant a confirmé sa présence
     *
     * @param RendezVous $appointment
     * @param User $participant
     */
    public function notifyOrganizerOfConfirmation(RendezVous $appointment, User $participant): void
    {
        $organizer = $appointment->getOrganizer();

        if (!$organizer->getEmail()) {
            $this->logger->warning('Impossible de notifier organisateur, email manquant', [
                'organizer_id' => $organizer->getId(),
                'appointment_id' => $appointment->getId()
            ]);
            return;
        }

        try {
            $email = (new TemplatedEmail())
                ->from(new Address($this->fromEmail, $this->fromName))
                ->to(new Address($organizer->getEmail(), $organizer->getFullName()))
                ->subject($participant->getFullName() . ' a confirmé sa présence')
                ->htmlTemplate('emails/appointment_presence_confirmed.html.twig')
                ->context([
                    'appointment' => $appointment,
                    'participant' => $participant,
                    'organizer' => $organizer,
                ]);

            $this->mailer->send($email);

            $this->logger->info('Notification confirmation présence envoyée à organisateur', [
                'organizer_id' => $organizer->getId(),
                'participant_id' => $participant->getId(),
                'appointment_id' => $appointment->getId()
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Erreur envoi notification confirmation à organisateur', [
                'organizer_id' => $organizer->getId(),
                'appointment_id' => $appointment->getId(),
                'error' => $e->getMessage()
            ]);
        }
    }
}
