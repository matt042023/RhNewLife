<?php

namespace App\Service;

use App\Entity\Invitation;
use App\Entity\User;
use App\Entity\Villa;
use App\Repository\InvitationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Psr\Log\LoggerInterface;

class InvitationManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private InvitationRepository $invitationRepository,
        private MailerInterface $mailer,
        private UrlGeneratorInterface $urlGenerator,
        private LoggerInterface $logger,
        private string $senderEmail = 'noreply@rhnewlife.fr',
        private string $senderName = 'RH NewLife'
    ) {}

    /**
     * Crée une nouvelle invitation et envoie l'email
     */
    public function createInvitation(
        string $email,
        string $firstName,
        string $lastName,
        ?string $position = null,
        bool $skipOnboarding = false,
        ?Villa $villa = null
    ): Invitation {
        $invitation = new Invitation();
        $invitation
            ->setEmail($email)
            ->setFirstName($firstName)
            ->setLastName($lastName)
            ->setPosition($position)
            ->setSkipOnboarding($skipOnboarding)
            ->setVilla($villa);

        $this->entityManager->persist($invitation);
        $this->entityManager->flush();

        try {
            $this->sendInvitationEmail($invitation);
        } catch (\Exception $e) {
            $invitation->markAsError('Erreur lors de l\'envoi de l\'email : ' . $e->getMessage());
            $this->entityManager->flush();

            $this->logger->error('Failed to send invitation email', [
                'invitation_id' => $invitation->getId(),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        return $invitation;
    }

    /**
     * Crée une invitation pour activation simple (sans onboarding complet)
     * Utilisé pour les créations manuelles de salariés par l'admin
     */
    public function createSimpleActivation(
        string $email,
        string $firstName,
        string $lastName,
        ?string $position = null,
        ?Villa $villa = null
    ): Invitation {
        return $this->createInvitation(
            $email,
            $firstName,
            $lastName,
            $position,
            true, // skipOnboarding = true
            $villa
        );
    }

    /**
     * Envoie l'email d'invitation
     */
    public function sendInvitationEmail(Invitation $invitation): void
    {
        $activationUrl = $this->urlGenerator->generate(
            'app_onboarding_activate',
            ['token' => $invitation->getToken()],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $email = (new TemplatedEmail())
            ->from(new Address($this->senderEmail, $this->senderName))
            ->to(new Address($invitation->getEmail(), $invitation->getFullName()))
            ->subject('Bienvenue chez RH NewLife - Activez votre compte')
            ->htmlTemplate('emails/invitation.html.twig')
            ->context([
                'invitation' => $invitation,
                'activationUrl' => $activationUrl,
                'expiresAt' => $invitation->getExpiresAt(),
            ]);

        $this->mailer->send($email);

        $this->logger->info('Invitation email sent', [
            'invitation_id' => $invitation->getId(),
            'email' => $invitation->getEmail(),
        ]);
    }

    /**
     * Relance une invitation existante
     */
    public function resendInvitation(Invitation $invitation): void
    {
        if ($invitation->getStatus() === Invitation::STATUS_USED) {
            throw new \LogicException('Cette invitation a déjà été utilisée.');
        }

        // Régénère le token et prolonge la date d'expiration
        $invitation
            ->setToken(bin2hex(random_bytes(32)))
            ->setExpiresAt((new \DateTime())->modify('+7 days'))
            ->setStatus(Invitation::STATUS_PENDING)
            ->setErrorMessage(null);

        $this->entityManager->flush();

        $this->sendInvitationEmail($invitation);
    }

    /**
     * Valide un token d'invitation
     */
    public function validateToken(string $token): ?Invitation
    {
        $invitation = $this->invitationRepository->findValidByToken($token);

        if (!$invitation) {
            return null;
        }

        // Marque comme expiré si nécessaire
        if ($invitation->isExpired()) {
            $invitation->markAsExpired();
            $this->entityManager->flush();
            return null;
        }

        return $invitation;
    }

    /**
     * Marque une invitation comme utilisée
     */
    public function markAsUsed(Invitation $invitation, User $user): void
    {
        $invitation->markAsUsed($user);
        $this->entityManager->flush();

        $this->logger->info('Invitation marked as used', [
            'invitation_id' => $invitation->getId(),
            'user_id' => $user->getId(),
        ]);
    }

    /**
     * Nettoie les invitations expirées (à exécuter via CRON)
     */
    public function cleanExpiredInvitations(): int
    {
        $expiredInvitations = $this->invitationRepository->findExpiredInvitations();
        $count = 0;

        foreach ($expiredInvitations as $invitation) {
            $invitation->markAsExpired();
            $count++;
        }

        $this->entityManager->flush();

        $this->logger->info('Expired invitations cleaned', ['count' => $count]);

        return $count;
    }

    /**
     * Envoie des rappels pour les invitations qui expirent bientôt
     */
    public function sendExpirationReminders(int $daysBeforeExpiry = 2): int
    {
        $invitations = $this->invitationRepository->findPendingInvitationsAboutToExpire($daysBeforeExpiry);
        $count = 0;

        foreach ($invitations as $invitation) {
            try {
                $this->sendReminderEmail($invitation);
                $count++;
            } catch (\Exception $e) {
                $this->logger->error('Failed to send reminder email', [
                    'invitation_id' => $invitation->getId(),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->logger->info('Expiration reminders sent', ['count' => $count]);

        return $count;
    }

    /**
     * Envoie un email de rappel
     */
    private function sendReminderEmail(Invitation $invitation): void
    {
        $activationUrl = $this->urlGenerator->generate(
            'app_onboarding_activate',
            ['token' => $invitation->getToken()],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $email = (new TemplatedEmail())
            ->from(new Address($this->senderEmail, $this->senderName))
            ->to(new Address($invitation->getEmail(), $invitation->getFullName()))
            ->subject('Rappel - Activez votre compte RH NewLife')
            ->htmlTemplate('emails/invitation_reminder.html.twig')
            ->context([
                'invitation' => $invitation,
                'activationUrl' => $activationUrl,
                'expiresAt' => $invitation->getExpiresAt(),
            ]);

        $this->mailer->send($email);
    }
}
