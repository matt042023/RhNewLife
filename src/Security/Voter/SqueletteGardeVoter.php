<?php

namespace App\Security\Voter;

use App\Entity\SqueletteGarde;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class SqueletteGardeVoter extends Voter
{
    public const VIEW = 'SQUELETTE_GARDE_VIEW';
    public const CREATE = 'SQUELETTE_GARDE_CREATE';
    public const EDIT = 'SQUELETTE_GARDE_EDIT';
    public const DELETE = 'SQUELETTE_GARDE_DELETE';
    public const DUPLICATE = 'SQUELETTE_GARDE_DUPLICATE';
    public const APPLY = 'SQUELETTE_GARDE_APPLY';

    protected function supports(string $attribute, mixed $subject): bool
    {
        if ($attribute === self::CREATE) {
            return true;
        }

        return in_array($attribute, [
            self::VIEW,
            self::EDIT,
            self::DELETE,
            self::DUPLICATE,
            self::APPLY
        ]) && $subject instanceof SqueletteGarde;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        // Only admins can manage templates
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
