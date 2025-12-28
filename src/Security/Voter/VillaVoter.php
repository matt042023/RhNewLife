<?php

namespace App\Security\Voter;

use App\Entity\User;
use App\Entity\Villa;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class VillaVoter extends Voter
{
    public const VIEW = 'VILLA_VIEW';
    public const CREATE = 'VILLA_CREATE';
    public const EDIT = 'VILLA_EDIT';
    public const DELETE = 'VILLA_DELETE';
    public const LIST = 'VILLA_LIST';

    protected function supports(string $attribute, mixed $subject): bool
    {
        // LIST and CREATE don't require a subject
        if (in_array($attribute, [self::LIST, self::CREATE])) {
            return true;
        }

        return in_array($attribute, [self::VIEW, self::EDIT, self::DELETE])
            && $subject instanceof Villa;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        return match ($attribute) {
            self::LIST => $this->canList($user),
            self::VIEW => $this->canView($user),
            self::CREATE => $this->canCreate($user),
            self::EDIT => $this->canEdit($user),
            self::DELETE => $this->canDelete($user, $subject),
            default => false,
        };
    }

    private function canList(User $user): bool
    {
        // Admin and Director can list villas
        return $this->hasRole($user, ['ROLE_ADMIN', 'ROLE_DIRECTOR']);
    }

    private function canView(User $user): bool
    {
        // Admin and Director can view villa details
        return $this->hasRole($user, ['ROLE_ADMIN', 'ROLE_DIRECTOR']);
    }

    private function canCreate(User $user): bool
    {
        // Only Admin can create villas
        return $this->hasRole($user, ['ROLE_ADMIN']);
    }

    private function canEdit(User $user): bool
    {
        // Only Admin can edit villas
        return $this->hasRole($user, ['ROLE_ADMIN']);
    }

    private function canDelete(User $user, Villa $villa): bool
    {
        // Only Admin can delete, and only if no users/affectations
        if (!$this->hasRole($user, ['ROLE_ADMIN'])) {
            return false;
        }

        return $villa->canBeDeleted();
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
