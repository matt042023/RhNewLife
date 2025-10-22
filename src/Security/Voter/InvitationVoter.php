<?php

namespace App\Security\Voter;

use App\Entity\Invitation;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class InvitationVoter extends Voter
{
    public const CREATE = 'INVITATION_CREATE';
    public const VIEW = 'INVITATION_VIEW';
    public const EDIT = 'INVITATION_EDIT';
    public const DELETE = 'INVITATION_DELETE';
    public const RESEND = 'INVITATION_RESEND';

    protected function supports(string $attribute, mixed $subject): bool
    {
        // Création : pas de subject
        if ($attribute === self::CREATE) {
            return true;
        }

        // Autres actions : subject doit être une Invitation
        return in_array($attribute, [self::VIEW, self::EDIT, self::DELETE, self::RESEND])
            && $subject instanceof Invitation;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        // L'utilisateur doit être authentifié
        if (!$user instanceof User) {
            return false;
        }

        // Seuls les admins peuvent gérer les invitations
        if (!in_array('ROLE_ADMIN', $user->getRoles())) {
            return false;
        }

        return match ($attribute) {
            self::CREATE => true,
            self::VIEW => true,
            self::EDIT => $this->canEdit($subject),
            self::DELETE => $this->canDelete($subject),
            self::RESEND => $this->canResend($subject),
            default => false,
        };
    }

    private function canEdit(Invitation $invitation): bool
    {
        // Ne peut modifier que les invitations en attente ou en erreur
        return in_array($invitation->getStatus(), [
            Invitation::STATUS_PENDING,
            Invitation::STATUS_ERROR,
        ]);
    }

    private function canDelete(Invitation $invitation): bool
    {
        // Peut supprimer les invitations non utilisées
        return $invitation->getStatus() !== Invitation::STATUS_USED;
    }

    private function canResend(Invitation $invitation): bool
    {
        // Peut renvoyer les invitations en attente, expirées ou en erreur
        return in_array($invitation->getStatus(), [
            Invitation::STATUS_PENDING,
            Invitation::STATUS_EXPIRED,
            Invitation::STATUS_ERROR,
        ]);
    }
}
