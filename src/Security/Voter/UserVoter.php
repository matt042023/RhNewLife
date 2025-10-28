<?php

namespace App\Security\Voter;

use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class UserVoter extends Voter
{
    public const VIEW = 'USER_VIEW';
    public const EDIT = 'USER_EDIT';
    public const EDIT_PROFILE = 'USER_EDIT_PROFILE';
    public const VALIDATE = 'USER_VALIDATE';
    public const ARCHIVE = 'USER_ARCHIVE';
    public const CREATE = 'USER_CREATE'; // EP-02: création manuelle
    public const REACTIVATE = 'USER_REACTIVATE'; // EP-02: réactivation

    protected function supports(string $attribute, mixed $subject): bool
    {
        // CREATE n'a pas de subject
        if ($attribute === self::CREATE) {
            return true;
        }

        return in_array($attribute, [
            self::VIEW,
            self::EDIT,
            self::EDIT_PROFILE,
            self::VALIDATE,
            self::ARCHIVE,
            self::REACTIVATE,
        ]) && $subject instanceof User;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $currentUser = $token->getUser();

        if (!$currentUser instanceof User) {
            return false;
        }

        /** @var User $targetUser */
        $targetUser = $subject;

        return match ($attribute) {
            self::VIEW => $this->canView($currentUser, $targetUser),
            self::EDIT => $this->canEdit($currentUser, $targetUser),
            self::EDIT_PROFILE => $this->canEditProfile($currentUser, $targetUser),
            self::VALIDATE => $this->canValidate($currentUser, $targetUser),
            self::ARCHIVE => $this->canArchive($currentUser),
            self::CREATE => $this->canCreate($currentUser),
            self::REACTIVATE => $this->canReactivate($currentUser, $targetUser),
            default => false,
        };
    }

    private function canView(User $currentUser, User $targetUser): bool
    {
        // Un utilisateur peut voir son propre profil
        if ($currentUser->getId() === $targetUser->getId()) {
            return true;
        }

        // Les admins et directeurs peuvent voir tous les profils
        return $this->hasRole($currentUser, ['ROLE_ADMIN', 'ROLE_DIRECTOR']);
    }

    private function canEdit(User $currentUser, User $targetUser): bool
    {
        // Seuls les admins peuvent modifier les profils complets
        return $this->hasRole($currentUser, ['ROLE_ADMIN']);
    }

    private function canEditProfile(User $currentUser, User $targetUser): bool
    {
        // Un utilisateur peut modifier ses propres informations limitées
        // (phone, address, IBAN - champs "self-modifiable")
        if ($currentUser->getId() === $targetUser->getId()) {
            return true;
        }

        // Les admins peuvent tout modifier
        return $this->hasRole($currentUser, ['ROLE_ADMIN']);
    }

    private function canValidate(User $currentUser, User $targetUser): bool
    {
        // Seuls les admins peuvent valider un onboarding
        if (!$this->hasRole($currentUser, ['ROLE_ADMIN'])) {
            return false;
        }

        // Le user doit être en statut ONBOARDING
        return $targetUser->getStatus() === User::STATUS_ONBOARDING;
    }

    private function canArchive(User $currentUser): bool
    {
        // Seuls les admins peuvent archiver
        return $this->hasRole($currentUser, ['ROLE_ADMIN']);
    }

    private function canCreate(User $currentUser): bool
    {
        // Seuls les admins peuvent créer manuellement des utilisateurs
        return $this->hasRole($currentUser, ['ROLE_ADMIN']);
    }

    private function canReactivate(User $currentUser, User $targetUser): bool
    {
        // Seuls les admins peuvent réactiver
        if (!$this->hasRole($currentUser, ['ROLE_ADMIN'])) {
            return false;
        }

        // L'utilisateur doit être archivé pour pouvoir être réactivé
        return $targetUser->getStatus() === User::STATUS_ARCHIVED;
    }

    private function hasRole(User $user, array|string $roles): bool
    {
        $roles = (array) $roles;
        $userRoles = $user->getRoles();

        foreach ($roles as $role) {
            if (in_array($role, $userRoles)) {
                return true;
            }
        }

        return false;
    }
}
