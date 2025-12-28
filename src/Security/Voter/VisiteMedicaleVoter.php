<?php

namespace App\Security\Voter;

use App\Entity\User;
use App\Entity\VisiteMedicale;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class VisiteMedicaleVoter extends Voter
{
    public const LIST = 'VISITE_MEDICALE_LIST';
    public const VIEW = 'VISITE_MEDICALE_VIEW';
    public const CREATE = 'VISITE_MEDICALE_CREATE';
    public const EDIT = 'VISITE_MEDICALE_EDIT';
    public const DELETE = 'VISITE_MEDICALE_DELETE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        // LIST and CREATE don't require a subject
        if (in_array($attribute, [self::LIST, self::CREATE])) {
            return true;
        }

        return in_array($attribute, [self::VIEW, self::EDIT, self::DELETE])
            && $subject instanceof VisiteMedicale;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        return match ($attribute) {
            self::LIST => $this->canList($user),
            self::VIEW => $this->canView($user, $subject),
            self::CREATE => $this->canCreate($user),
            self::EDIT => $this->canEdit($user, $subject),
            self::DELETE => $this->canDelete($user, $subject),
            default => false,
        };
    }

    private function canList(User $user): bool
    {
        // Admin and Director can list all visits
        return $this->hasRole($user, ['ROLE_ADMIN', 'ROLE_DIRECTOR']);
    }

    private function canView(User $user, ?VisiteMedicale $visit): bool
    {
        if (!$visit) {
            return false;
        }

        // Owner can view their own visits
        if ($visit->getUser() === $user) {
            return true;
        }

        // Admin and Director can view all
        return $this->hasRole($user, ['ROLE_ADMIN', 'ROLE_DIRECTOR']);
    }

    private function canCreate(User $user): bool
    {
        // Only Admin can create medical visits
        return $this->hasRole($user, ['ROLE_ADMIN']);
    }

    private function canEdit(User $user, ?VisiteMedicale $visit): bool
    {
        if (!$visit) {
            return false;
        }

        // Only Admin can edit medical visits
        return $this->hasRole($user, ['ROLE_ADMIN']);
    }

    private function canDelete(User $user, ?VisiteMedicale $visit): bool
    {
        if (!$visit) {
            return false;
        }

        // Only Admin can delete medical visits
        return $this->hasRole($user, ['ROLE_ADMIN']);
    }

    private function hasRole(User $user, array|string $roles): bool
    {
        $roles = (array) $roles;
        $userRoles = $user->getRoles();

        foreach ($roles as $role) {
            if (in_array($role, $userRoles, true)) {
                return true;
            }
        }

        return false;
    }
}
