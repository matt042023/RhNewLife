<?php

namespace App\Service\Absence;

use App\Entity\Absence;
use App\Entity\Document;
use App\Entity\User;
use App\Repository\UserRepository;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class AbsenceNotificationService
{
    public function __construct(
        private MailerInterface $mailer,
        private UserRepository $userRepository,
        private UrlGeneratorInterface $urlGenerator,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Notify admin when absence is created
     */
    public function notifyAbsenceCreated(Absence $absence): void
    {
        try {
            $admins = $this->userRepository->findByRole('ROLE_ADMIN');

            if (empty($admins)) {
                $this->logger->warning('No admin users found to notify about absence creation', [
                    'absence_id' => $absence->getId(),
                ]);
                return;
            }

            $url = $this->urlGenerator->generate(
                'admin_absence_show',
                ['id' => $absence->getId()],
                UrlGeneratorInterface::ABSOLUTE_URL
            );

            foreach ($admins as $admin) {
                $email = (new TemplatedEmail())
                    ->from(new Address('noreply@rhnewlife.com', 'RH NewLife'))
                    ->to(new Address($admin->getEmail(), $admin->getFullName()))
                    ->replyTo(new Address('rh@rhnewlife.com', 'Service RH'))
                    ->subject('Nouvelle demande d\'absence à valider')
                    ->htmlTemplate('emails/absence/absence_created.html.twig')
                    ->context([
                        'absence' => $absence,
                        'user' => $absence->getUser(),
                        'admin' => $admin,
                        'url' => $url,
                        'currentYear' => date('Y'),
                    ]);

                $this->mailer->send($email);
            }

            $this->logger->info('Absence creation notification sent', [
                'absence_id' => $absence->getId(),
                'admin_count' => count($admins),
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to send absence creation notification', [
                'absence_id' => $absence->getId(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Notify user when absence is validated
     */
    public function notifyAbsenceValidated(Absence $absence): void
    {
        try {
            $user = $absence->getUser();

            if (!$user || !$user->getEmail()) {
                return;
            }

            $url = $this->urlGenerator->generate(
                'app_absence_show',
                ['id' => $absence->getId()],
                UrlGeneratorInterface::ABSOLUTE_URL
            );

            $email = (new TemplatedEmail())
                ->from(new Address('noreply@rhnewlife.com', 'RH NewLife'))
                ->to(new Address($user->getEmail(), $user->getFullName()))
                ->replyTo(new Address('rh@rhnewlife.com', 'Service RH'))
                ->subject('✅ Votre demande d\'absence a été validée')
                ->htmlTemplate('emails/absence/absence_validated.html.twig')
                ->context([
                    'absence' => $absence,
                    'user' => $user,
                    'validator' => $absence->getValidatedBy(),
                    'url' => $url,
                    'currentYear' => date('Y'),
                ]);

            $this->mailer->send($email);

            $this->logger->info('Absence validation notification sent', [
                'absence_id' => $absence->getId(),
                'user_id' => $user->getId(),
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to send absence validation notification', [
                'absence_id' => $absence->getId(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Notify user when absence is rejected
     */
    public function notifyAbsenceRejected(Absence $absence, string $reason): void
    {
        try {
            $user = $absence->getUser();

            if (!$user || !$user->getEmail()) {
                return;
            }

            $url = $this->urlGenerator->generate(
                'app_absence_show',
                ['id' => $absence->getId()],
                UrlGeneratorInterface::ABSOLUTE_URL
            );

            $email = (new TemplatedEmail())
                ->from(new Address('noreply@rhnewlife.com', 'RH NewLife'))
                ->to(new Address($user->getEmail(), $user->getFullName()))
                ->replyTo(new Address('rh@rhnewlife.com', 'Service RH'))
                ->subject('❌ Demande d\'absence refusée')
                ->htmlTemplate('emails/absence/absence_rejected.html.twig')
                ->context([
                    'absence' => $absence,
                    'user' => $user,
                    'reason' => $reason,
                    'validator' => $absence->getValidatedBy(),
                    'url' => $url,
                    'currentYear' => date('Y'),
                ]);

            $this->mailer->send($email);

            $this->logger->info('Absence rejection notification sent', [
                'absence_id' => $absence->getId(),
                'user_id' => $user->getId(),
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to send absence rejection notification', [
                'absence_id' => $absence->getId(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Notify admin when justification is uploaded
     */
    public function notifyJustificationUploaded(Absence $absence, Document $document): void
    {
        try {
            $admins = $this->userRepository->findByRole('ROLE_ADMIN');

            if (empty($admins)) {
                return;
            }

            $url = $this->urlGenerator->generate(
                'admin_absence_show',
                ['id' => $absence->getId()],
                UrlGeneratorInterface::ABSOLUTE_URL
            );

            foreach ($admins as $admin) {
                $email = (new TemplatedEmail())
                    ->from(new Address('noreply@rhnewlife.com', 'RH NewLife'))
                    ->to(new Address($admin->getEmail(), $admin->getFullName()))
                    ->replyTo(new Address('rh@rhnewlife.com', 'Service RH'))
                    ->subject('Nouveau justificatif à valider')
                    ->htmlTemplate('emails/absence/justification_uploaded.html.twig')
                    ->context([
                        'absence' => $absence,
                        'document' => $document,
                        'user' => $absence->getUser(),
                        'admin' => $admin,
                        'url' => $url,
                        'currentYear' => date('Y'),
                    ]);

                $this->mailer->send($email);
            }

            $this->logger->info('Justification upload notification sent', [
                'absence_id' => $absence->getId(),
                'document_id' => $document->getId(),
                'admin_count' => count($admins),
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to send justification upload notification', [
                'absence_id' => $absence->getId(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Notify user when justification is validated
     */
    public function notifyJustificationValidated(Absence $absence, Document $document): void
    {
        try {
            $user = $absence->getUser();

            if (!$user || !$user->getEmail()) {
                return;
            }

            $url = $this->urlGenerator->generate(
                'app_absence_show',
                ['id' => $absence->getId()],
                UrlGeneratorInterface::ABSOLUTE_URL
            );

            $email = (new TemplatedEmail())
                ->from(new Address('noreply@rhnewlife.com', 'RH NewLife'))
                ->to(new Address($user->getEmail(), $user->getFullName()))
                ->replyTo(new Address('rh@rhnewlife.com', 'Service RH'))
                ->subject('✅ Votre justificatif a été validé')
                ->htmlTemplate('emails/absence/justification_validated.html.twig')
                ->context([
                    'absence' => $absence,
                    'document' => $document,
                    'user' => $user,
                    'validator' => $document->getValidatedBy(),
                    'url' => $url,
                    'currentYear' => date('Y'),
                ]);

            $this->mailer->send($email);

            $this->logger->info('Justification validation notification sent', [
                'absence_id' => $absence->getId(),
                'document_id' => $document->getId(),
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to send justification validation notification', [
                'absence_id' => $absence->getId(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Notify user when justification is rejected
     */
    public function notifyJustificationRejected(Absence $absence, Document $document, string $reason): void
    {
        try {
            $user = $absence->getUser();

            if (!$user || !$user->getEmail()) {
                return;
            }

            $url = $this->urlGenerator->generate(
                'app_absence_show',
                ['id' => $absence->getId()],
                UrlGeneratorInterface::ABSOLUTE_URL
            );

            $email = (new TemplatedEmail())
                ->from(new Address('noreply@rhnewlife.com', 'RH NewLife'))
                ->to(new Address($user->getEmail(), $user->getFullName()))
                ->replyTo(new Address('rh@rhnewlife.com', 'Service RH'))
                ->subject('⚠️ Justificatif refusé - Action requise')
                ->htmlTemplate('emails/absence/justification_rejected.html.twig')
                ->context([
                    'absence' => $absence,
                    'document' => $document,
                    'user' => $user,
                    'reason' => $reason,
                    'newDeadline' => $absence->getJustificationDeadline(),
                    'url' => $url,
                    'currentYear' => date('Y'),
                ]);

            $this->mailer->send($email);

            $this->logger->info('Justification rejection notification sent', [
                'absence_id' => $absence->getId(),
                'document_id' => $document->getId(),
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to send justification rejection notification', [
                'absence_id' => $absence->getId(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send reminder for justification deadline (J-1)
     */
    public function notifyJustificationDeadlineReminder(Absence $absence): void
    {
        try {
            $user = $absence->getUser();

            if (!$user || !$user->getEmail()) {
                return;
            }

            $url = $this->urlGenerator->generate(
                'app_absence_show',
                ['id' => $absence->getId()],
                UrlGeneratorInterface::ABSOLUTE_URL
            );

            $email = (new TemplatedEmail())
                ->from(new Address('noreply@rhnewlife.com', 'RH NewLife'))
                ->to(new Address($user->getEmail(), $user->getFullName()))
                ->replyTo(new Address('rh@rhnewlife.com', 'Service RH'))
                ->subject('⏰ Rappel : justificatif à fournir demain')
                ->htmlTemplate('emails/absence/justification_reminder.html.twig')
                ->context([
                    'absence' => $absence,
                    'user' => $user,
                    'deadline' => $absence->getJustificationDeadline(),
                    'url' => $url,
                    'currentYear' => date('Y'),
                ]);

            $this->mailer->send($email);

            $this->logger->info('Justification reminder sent', [
                'absence_id' => $absence->getId(),
                'user_id' => $user->getId(),
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to send justification reminder', [
                'absence_id' => $absence->getId(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Notify admin when justification deadline is overdue
     */
    public function notifyJustificationOverdue(Absence $absence): void
    {
        try {
            $admins = $this->userRepository->findByRole('ROLE_ADMIN');

            if (empty($admins)) {
                return;
            }

            $url = $this->urlGenerator->generate(
                'admin_absence_show',
                ['id' => $absence->getId()],
                UrlGeneratorInterface::ABSOLUTE_URL
            );

            foreach ($admins as $admin) {
                $email = (new TemplatedEmail())
                    ->from(new Address('noreply@rhnewlife.com', 'RH NewLife'))
                    ->to(new Address($admin->getEmail(), $admin->getFullName()))
                    ->replyTo(new Address('rh@rhnewlife.com', 'Service RH'))
                    ->subject('⚠️ Justificatif non fourni - Échéance dépassée')
                    ->htmlTemplate('emails/absence/justification_overdue.html.twig')
                    ->context([
                        'absence' => $absence,
                        'user' => $absence->getUser(),
                        'admin' => $admin,
                        'deadline' => $absence->getJustificationDeadline(),
                        'url' => $url,
                        'currentYear' => date('Y'),
                    ]);

                $this->mailer->send($email);
            }

            $this->logger->info('Justification overdue notification sent', [
                'absence_id' => $absence->getId(),
                'admin_count' => count($admins),
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to send justification overdue notification', [
                'absence_id' => $absence->getId(),
                'error' => $e->getMessage(),
            ]);
        }
    }
}
