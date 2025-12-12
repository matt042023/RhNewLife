<?php

namespace App\Security\Voter;

use App\Entity\RendezVous;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class AppointmentVoter extends Voter
{
    public const VIEW = 'APPOINTMENT_VIEW';
    public const EDIT = 'APPOINTMENT_EDIT';
    public const DELETE = 'APPOINTMENT_DELETE';
    public const VALIDATE = 'APPOINTMENT_VALIDATE';
    public const CONFIRM_PRESENCE = 'APPOINTMENT_CONFIRM_PRESENCE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [
            self::VIEW,
            self::EDIT,
            self::DELETE,
            self::VALIDATE,
            self::CONFIRM_PRESENCE
        ]) && $subject instanceof RendezVous;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        /** @var RendezVous $appointment */
        $appointment = $subject;

        return match ($attribute) {
            self::VIEW => $this->canView($appointment, $user),
            self::EDIT => $this->canEdit($appointment, $user),
            self::DELETE => $this->canDelete($appointment, $user),
            self::VALIDATE => $this->canValidate($appointment, $user),
            self::CONFIRM_PRESENCE => $this->canConfirmPresence($appointment, $user),
            default => false,
        };
    }

    /**
     * Admin/Director voient tout, users voient leurs RDV (participant ou organisateur)
     */
    private function canView(RendezVous $appointment, User $user): bool
    {
        // Admin et Director peuvent tout voir
        if ($this->isAdminOrDirector($user)) {
            return true;
        }

        // L'utilisateur peut voir s'il est organisateur
        if ($appointment->getOrganizer() === $user) {
            return true;
        }

        // L'utilisateur peut voir s'il est créateur
        if ($appointment->getCreatedBy() === $user) {
            return true;
        }

        // L'utilisateur peut voir s'il est participant
        if ($appointment->hasParticipant($user)) {
            return true;
        }

        return false;
    }

    /**
     * Admin ou organisateur uniquement, pas si TERMINE ou REFUSE
     */
    private function canEdit(RendezVous $appointment, User $user): bool
    {
        // Ne peut pas éditer si terminé ou refusé
        if (in_array($appointment->getStatut(), [
            RendezVous::STATUS_TERMINE,
            RendezVous::STATUS_REFUSE
        ])) {
            return false;
        }

        // Admin peut éditer
        if ($this->isAdmin($user)) {
            return true;
        }

        // L'organisateur peut éditer son propre RDV
        if ($appointment->getOrganizer() === $user) {
            return true;
        }

        return false;
    }

    /**
     * Admin ou organisateur, si EN_ATTENTE ou CONFIRME
     */
    private function canDelete(RendezVous $appointment, User $user): bool
    {
        // Ne peut supprimer que si EN_ATTENTE ou CONFIRME
        if (!in_array($appointment->getStatut(), [
            RendezVous::STATUS_EN_ATTENTE,
            RendezVous::STATUS_CONFIRME
        ])) {
            return false;
        }

        // Admin peut supprimer
        if ($this->isAdmin($user)) {
            return true;
        }

        // L'organisateur peut supprimer son propre RDV
        if ($appointment->getOrganizer() === $user) {
            return true;
        }

        return false;
    }

    /**
     * Admin/Director seulement, pour DEMANDE avec statut EN_ATTENTE
     */
    private function canValidate(RendezVous $appointment, User $user): bool
    {
        // Seuls Admin et Director peuvent valider
        if (!$this->isAdminOrDirector($user)) {
            return false;
        }

        // Ne peut valider que les demandes
        if ($appointment->getType() !== RendezVous::TYPE_DEMANDE) {
            return false;
        }

        // Ne peut valider que si EN_ATTENTE
        if ($appointment->getStatut() !== RendezVous::STATUS_EN_ATTENTE) {
            return false;
        }

        return true;
    }

    /**
     * Admin pour tous, user pour lui-même
     */
    private function canConfirmPresence(RendezVous $appointment, User $user): bool
    {
        // Admin peut confirmer pour tout le monde
        if ($this->isAdmin($user)) {
            return true;
        }

        // L'utilisateur peut confirmer sa propre présence
        if ($appointment->hasParticipant($user)) {
            return true;
        }

        return false;
    }

    private function isAdmin(User $user): bool
    {
        return in_array('ROLE_ADMIN', $user->getRoles());
    }

    private function isDirector(User $user): bool
    {
        return in_array('ROLE_DIRECTOR', $user->getRoles());
    }

    private function isAdminOrDirector(User $user): bool
    {
        return $this->isAdmin($user) || $this->isDirector($user);
    }
}
