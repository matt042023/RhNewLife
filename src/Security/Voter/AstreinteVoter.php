<?php

namespace App\Security\Voter;

use App\Entity\Astreinte;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class AstreinteVoter extends Voter
{
    public const VIEW = 'ASTREINTE_VIEW';
    public const CREATE = 'ASTREINTE_CREATE';
    public const EDIT = 'ASTREINTE_EDIT';
    public const DELETE = 'ASTREINTE_DELETE';
    public const ASSIGN = 'ASTREINTE_ASSIGN';
    public const RECORD_REPLACEMENT = 'ASTREINTE_RECORD_REPLACEMENT';

    protected function supports(string $attribute, mixed $subject): bool
    {
        if ($attribute === self::CREATE) {
            return true; // No subject needed for CREATE
        }

        return in_array($attribute, [
            self::VIEW,
            self::EDIT,
            self::DELETE,
            self::ASSIGN,
            self::RECORD_REPLACEMENT
        ]) && $subject instanceof Astreinte;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        return match ($attribute) {
            self::VIEW => $this->canView($user),
            self::CREATE => $this->canCreate($user),
            self::EDIT => $this->canEdit($user),
            self::DELETE => $this->canDelete($user),
            self::ASSIGN => $this->canAssign($user),
            self::RECORD_REPLACEMENT => $this->canRecordReplacement($user),
            default => false,
        };
    }

    /**
     * All authenticated users can view astreintes
     */
    private function canView(User $user): bool
    {
        return true; // Everyone can see the on-call planning
    }

    /**
     * Only admins can create astreintes
     */
    private function canCreate(User $user): bool
    {
        return $this->hasRole($user, ['ROLE_ADMIN']);
    }

    /**
     * Only admins can edit astreintes
     */
    private function canEdit(User $user): bool
    {
        return $this->hasRole($user, ['ROLE_ADMIN']);
    }

    /**
     * Only admins can delete astreintes
     */
    private function canDelete(User $user): bool
    {
        return $this->hasRole($user, ['ROLE_ADMIN']);
    }

    /**
     * Only admins can assign educators
     */
    private function canAssign(User $user): bool
    {
        return $this->hasRole($user, ['ROLE_ADMIN']);
    }

    /**
     * Only admins can record replacements
     */
    private function canRecordReplacement(User $user): bool
    {
        return $this->hasRole($user, ['ROLE_ADMIN']);
    }

    private function hasRole(User $user, array $roles): bool
    {
        foreach ($roles as $role) {
            if (in_array($role, $user->getRoles(), true)) {
                return true;
            }
        }
        return false;
    }
}
