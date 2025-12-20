<?php

namespace App\Service;

use App\Entity\Invitation;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Psr\Log\LoggerInterface;

class OnboardingManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserRepository $userRepository,
        private UserPasswordHasherInterface $passwordHasher,
        private InvitationManager $invitationManager,
        private MailerInterface $mailer,
        private LoggerInterface $logger,
        private string $senderEmail = 'noreply@rhnewlife.fr',
        private string $senderName = 'RH NewLife'
    ) {
    }

    /**
     * Active un compte utilisateur depuis une invitation
     */
    public function activateAccount(
        Invitation $invitation,
        string $plainPassword,
        bool $acceptCGU
    ): User {
        if (!$acceptCGU) {
            throw new \InvalidArgumentException('Vous devez accepter les CGU pour continuer.');
        }

        // Validation du mot de passe
        $this->validatePassword($plainPassword);

        // Vérifier si l'utilisateur existe déjà (création manuelle par admin)
        $user = $this->userRepository->findOneBy(['email' => $invitation->getEmail()]);

        if ($user) {
            // L'utilisateur existe déjà (création manuelle) - juste mettre à jour le mot de passe et le statut
            $this->logger->info('Activating existing user', [
                'user_id' => $user->getId(),
                'email' => $user->getEmail(),
            ]);
        } else {
            // Création d'un nouvel utilisateur (ancien flux)
            $user = new User();
            $user
                ->setEmail($invitation->getEmail())
                ->setFirstName($invitation->getFirstName())
                ->setLastName($invitation->getLastName())
                ->setPosition($invitation->getPosition())
                ->setVilla($invitation->getVilla())
                ->setRoles(['ROLE_USER']);

            $this->entityManager->persist($user);
        }

        // Mettre à jour le statut et le mot de passe (pour les deux cas)
        $user->setStatus(User::STATUS_ONBOARDING);
        $user->setCguAcceptedAt(new \DateTime());

        // Hash du mot de passe
        $hashedPassword = $this->passwordHasher->hashPassword($user, $plainPassword);
        $user->setPassword($hashedPassword);

        // Sauvegarde
        $this->entityManager->flush();

        // Marque l'invitation comme utilisée
        $this->invitationManager->markAsUsed($invitation, $user);

        // Envoie l'email de confirmation
        $this->sendAccountActivatedEmail($user);

        $this->logger->info('User account activated', [
            'user_id' => $user->getId(),
            'invitation_id' => $invitation->getId(),
        ]);

        return $user;
    }

    /**
     * Active un compte utilisateur en mode simple (sans onboarding complet)
     * Utilisé pour les créations manuelles de salariés par l'admin
     * Le compte est directement actif, sans passer par le processus d'onboarding
     */
    public function activateSimpleAccount(
        Invitation $invitation,
        string $plainPassword,
        bool $acceptCGU
    ): User {
        if (!$acceptCGU) {
            throw new \InvalidArgumentException('Vous devez accepter les CGU pour continuer.');
        }

        if (!$invitation->isSkipOnboarding()) {
            throw new \LogicException('Cette invitation nécessite un onboarding complet.');
        }

        // Validation du mot de passe
        $this->validatePassword($plainPassword);

        // Création de l'utilisateur directement actif
        $user = new User();
        $user
            ->setEmail($invitation->getEmail())
            ->setFirstName($invitation->getFirstName())
            ->setLastName($invitation->getLastName())
            ->setPosition($invitation->getPosition())
            ->setVilla($invitation->getVilla())
            ->setStatus(User::STATUS_ACTIVE) // Directement actif
            ->setRoles(['ROLE_USER'])
            ->setCguAcceptedAt(new \DateTime());

        // Hash du mot de passe
        $hashedPassword = $this->passwordHasher->hashPassword($user, $plainPassword);
        $user->setPassword($hashedPassword);

        // Sauvegarde
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        // Marque l'invitation comme utilisée
        $this->invitationManager->markAsUsed($invitation, $user);

        // Envoie l'email de bienvenue
        $this->sendWelcomeEmail($user);

        $this->logger->info('User account activated (simple mode)', [
            'user_id' => $user->getId(),
            'invitation_id' => $invitation->getId(),
        ]);

        return $user;
    }

    /**
     * Met à jour le profil utilisateur (informations personnelles)
     */
    public function updateProfile(
        User $user,
        array $profileData
    ): User {
        // Mise à jour des champs autorisés
        if (isset($profileData['phone'])) {
            $user->setPhone($profileData['phone']);
        }

        if (isset($profileData['address'])) {
            $user->setAddress($profileData['address']);
        }

        if (isset($profileData['familyStatus'])) {
            $user->setFamilyStatus($profileData['familyStatus']);
        }

        if (isset($profileData['children'])) {
            $user->setChildren((int) $profileData['children']);
        }

        if (isset($profileData['iban'])) {
            $user->setIban($profileData['iban']);
        }

        if (isset($profileData['bic'])) {
            $user->setBic($profileData['bic']);
        }

        $this->entityManager->flush();

        $this->logger->info('User profile updated', [
            'user_id' => $user->getId(),
        ]);

        return $user;
    }

    /**
     * Valide la force d'un mot de passe
     */
    public function validatePassword(string $password): void
    {
        $errors = [];

        // Longueur minimale
        if (strlen($password) < 12) {
            $errors[] = 'Le mot de passe doit contenir au moins 12 caractères.';
        }

        // Au moins une majuscule
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Le mot de passe doit contenir au moins une lettre majuscule.';
        }

        // Au moins une minuscule
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'Le mot de passe doit contenir au moins une lettre minuscule.';
        }

        // Au moins un chiffre
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'Le mot de passe doit contenir au moins un chiffre.';
        }

        // Au moins un caractère spécial
        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            $errors[] = 'Le mot de passe doit contenir au moins un caractère spécial.';
        }

        if (!empty($errors)) {
            throw new \InvalidArgumentException(implode(' ', $errors));
        }
    }

    /**
     * Calcule la force d'un mot de passe (0-100)
     */
    public function calculatePasswordStrength(string $password): int
    {
        $strength = 0;

        // Longueur
        $length = strlen($password);
        if ($length >= 8) $strength += 20;
        if ($length >= 12) $strength += 10;
        if ($length >= 16) $strength += 10;

        // Complexité
        if (preg_match('/[a-z]/', $password)) $strength += 15;
        if (preg_match('/[A-Z]/', $password)) $strength += 15;
        if (preg_match('/[0-9]/', $password)) $strength += 15;
        if (preg_match('/[^A-Za-z0-9]/', $password)) $strength += 15;

        return min(100, $strength);
    }

    /**
     * Vérifie si le dossier d'onboarding est complet
     * Un dossier est complet si :
     * - Les informations personnelles sont remplies
     * - Les coordonnées bancaires sont renseignées
     * - Les documents obligatoires sont uploadés
     *
     * Note: Le contrat est généré par l'admin après validation du dossier
     */
    public function isOnboardingComplete(User $user): bool
    {
        // Vérifie que toutes les informations obligatoires sont présentes
        if (!$user->getPhone() || !$user->getAddress()) {
            return false;
        }

        if (!$user->getIban() || !$user->getBic()) {
            return false;
        }

        // Vérifie que tous les documents obligatoires sont uploadés
        // Cette vérification sera complétée avec le DocumentManager

        return true;
    }

    /**
     * Finalise l'onboarding et notifie l'admin
     */
    public function completeOnboarding(User $user): void
    {
        if (!$this->isOnboardingComplete($user)) {
            throw new \LogicException('Le dossier d\'onboarding n\'est pas complet.');
        }

        // Empêcher la re-soumission
        if ($user->isSubmitted()) {
            throw new \LogicException('Le dossier a déjà été soumis et est en attente de validation.');
        }

        // Marquer comme soumis
        $user->setSubmittedAt(new \DateTime());
        $this->entityManager->flush();

        // Le statut reste en ONBOARDING jusqu'à validation admin
        // Notification admin sera envoyée ici
        $this->sendOnboardingCompletedEmailToAdmin($user);

        $this->logger->info('Onboarding completed, waiting for admin validation', [
            'user_id' => $user->getId(),
        ]);
    }

    /**
     * Valide le dossier d'onboarding (admin)
     */
    public function validateOnboarding(User $user, array $rejectedDocuments = []): void
    {
        $user->setStatus(User::STATUS_ACTIVE);
        $this->entityManager->flush();

        // Envoie email en fonction du statut des documents
        if (empty($rejectedDocuments)) {
            // Tous les documents sont validés
            $this->sendWelcomeEmail($user);
        } else {
            // Certains documents sont rejetés
            $this->sendOnboardingValidatedWithRejectionsEmail($user, $rejectedDocuments);
        }

        $this->logger->info('Onboarding validated by admin', [
            'user_id' => $user->getId(),
            'rejected_documents_count' => count($rejectedDocuments),
        ]);
    }

    /**
     * Renvoyer le dossier en modification (admin demande des corrections)
     */
    public function requestChanges(User $user, string $reason): void
    {
        if ($user->getStatus() !== User::STATUS_ONBOARDING) {
            throw new \LogicException('Seuls les dossiers en attente peuvent être renvoyés en modification.');
        }

        // Réinitialiser la date de soumission pour permettre les modifications
        $user->setSubmittedAt(null);
        $this->entityManager->flush();

        // TODO: Envoyer un email à l'utilisateur avec la raison du rejet
        $this->logger->info('Onboarding sent back for changes', [
            'user_id' => $user->getId(),
            'reason' => $reason,
        ]);
    }

    /**
     * Envoie l'email de confirmation d'activation de compte
     */
    private function sendAccountActivatedEmail(User $user): void
    {
        $email = (new TemplatedEmail())
            ->from(new Address($this->senderEmail, $this->senderName))
            ->to(new Address($user->getEmail(), $user->getFullName()))
            ->subject('Votre compte RH NewLife est activé')
            ->htmlTemplate('emails/account_activated.html.twig')
            ->context([
                'user' => $user,
            ]);

        $this->mailer->send($email);
    }

    /**
     * Envoie une notification à l'admin qu'un onboarding est complet
     */
    private function sendOnboardingCompletedEmailToAdmin(User $user): void
    {
        // TODO: Récupérer l'email admin depuis la config
        $adminEmail = 'admin@rhnewlife.fr';

        $email = (new TemplatedEmail())
            ->from(new Address($this->senderEmail, $this->senderName))
            ->to($adminEmail)
            ->subject('Nouveau dossier d\'onboarding à valider')
            ->htmlTemplate('emails/onboarding_completed.html.twig')
            ->context([
                'user' => $user,
            ]);

        $this->mailer->send($email);
    }

    /**
     * Envoie l'email de bienvenue définitif
     */
    private function sendWelcomeEmail(User $user): void
    {
        $email = (new TemplatedEmail())
            ->from(new Address($this->senderEmail, $this->senderName))
            ->to(new Address($user->getEmail(), $user->getFullName()))
            ->subject('Bienvenue dans l\'équipe RH NewLife !')
            ->htmlTemplate('emails/onboarding_validated.html.twig')
            ->context([
                'user' => $user,
            ]);

        $this->mailer->send($email);
    }

    /**
     * Envoie l'email de validation avec liste des documents rejetés
     */
    private function sendOnboardingValidatedWithRejectionsEmail(User $user, array $rejectedDocuments): void
    {
        $email = (new TemplatedEmail())
            ->from(new Address($this->senderEmail, $this->senderName))
            ->to(new Address($user->getEmail(), $user->getFullName()))
            ->subject('Votre compte RH NewLife est activé - Action requise')
            ->htmlTemplate('emails/onboarding_validated_with_rejections.html.twig')
            ->context([
                'user' => $user,
                'rejectedDocuments' => $rejectedDocuments,
            ]);

        $this->mailer->send($email);
    }
}
