<?php

namespace App\Security\Voter;

use App\Entity\Absence;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class AbsenceVoter extends Voter
{
    public const VIEW = 'ABSENCE_VIEW';
    public const EDIT = 'ABSENCE_EDIT';
    public const DELETE = 'ABSENCE_DELETE';
    public const VALIDATE = 'ABSENCE_VALIDATE';
    public const CANCEL = 'ABSENCE_CANCEL';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::EDIT, self::DELETE, self::VALIDATE, self::CANCEL])
            && $subject instanceof Absence;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        /** @var Absence $absence */
        $absence = $subject;

        return match ($attribute) {
            self::VIEW => $this->canView($absence, $user),
            self::EDIT => $this->canEdit($absence, $user),
            self::DELETE => $this->canDelete($absence, $user),
            self::VALIDATE => $this->canValidate($absence, $user),
            self::CANCEL => $this->canCancel($absence, $user),
            default => false,
        };
    }

    /**
     * User can view absence if:
     * - Owner
     * - Admin
     * - Director
     */
    private function canView(Absence $absence, User $user): bool
    {
        // Owner can view
        if ($absence->getUser() === $user) {
            return true;
        }

        // Admin and Director can view all
        return $this->hasRole($user, ['ROLE_ADMIN', 'ROLE_DIRECTOR']);
    }

    /**
     * User can edit absence if:
     * - Owner AND status is PENDING
     */
    private function canEdit(Absence $absence, User $user): bool
    {
        // Only owner can edit
        if ($absence->getUser() !== $user) {
            return false;
        }

        // Can only edit if pending
        return $absence->isPending();
    }

    /**
     * User can delete absence if:
     * - Owner AND status is PENDING
     */
    private function canDelete(Absence $absence, User $user): bool
    {
        // Only owner can delete
        if ($absence->getUser() !== $user) {
            return false;
        }

        // Can only delete if pending
        return $absence->isPending();
    }

    /**
     * Only admin can validate/reject absences
     */
    private function canValidate(Absence $absence, User $user): bool
    {
        return $this->hasRole($user, ['ROLE_ADMIN']);
    }

    /**
     * User can cancel absence if:
     * - Owner AND (status is PENDING or APPROVED)
     */
    private function canCancel(Absence $absence, User $user): bool
    {
        // Only owner can cancel
        if ($absence->getUser() !== $user) {
            return false;
        }

        // Can cancel if pending or approved
        return $absence->canBeCancelled();
    }

    /**
     * Check if user has any of the specified roles
     */
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
