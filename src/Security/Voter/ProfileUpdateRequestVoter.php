<?php

namespace App\Security\Voter;

use App\Entity\ProfileUpdateRequest;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class ProfileUpdateRequestVoter extends Voter
{
    public const VIEW = 'PROFILE_UPDATE_REQUEST_VIEW';
    public const APPROVE = 'PROFILE_UPDATE_REQUEST_APPROVE';
    public const REJECT = 'PROFILE_UPDATE_REQUEST_REJECT';
    public const CREATE = 'PROFILE_UPDATE_REQUEST_CREATE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        // CREATE n'a pas de subject
        if ($attribute === self::CREATE) {
            return true;
        }

        return in_array($attribute, [
            self::VIEW,
            self::APPROVE,
            self::REJECT,
        ]) && $subject instanceof ProfileUpdateRequest;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $currentUser = $token->getUser();

        if (!$currentUser instanceof User) {
            return false;
        }

        return match ($attribute) {
            self::CREATE => $this->canCreate($currentUser),
            self::VIEW => $this->canView($currentUser, $subject),
            self::APPROVE => $this->canApprove($currentUser, $subject),
            self::REJECT => $this->canReject($currentUser, $subject),
            default => false,
        };
    }

    private function canCreate(User $currentUser): bool
    {
        // Tous les utilisateurs peuvent créer des demandes de modification
        // (pour eux-mêmes uniquement, vérifié dans le service)
        return true;
    }

    private function canView(User $currentUser, ProfileUpdateRequest $request): bool
    {
        // Un utilisateur peut voir ses propres demandes
        if ($request->getUser()->getId() === $currentUser->getId()) {
            return true;
        }

        // Les admins peuvent voir toutes les demandes
        return $this->hasRole($currentUser, 'ROLE_ADMIN');
    }

    private function canApprove(User $currentUser, ProfileUpdateRequest $request): bool
    {
        // Seuls les admins peuvent approuver
        if (!$this->hasRole($currentUser, 'ROLE_ADMIN')) {
            return false;
        }

        // La demande doit être en attente
        return $request->getStatus() === ProfileUpdateRequest::STATUS_PENDING;
    }

    private function canReject(User $currentUser, ProfileUpdateRequest $request): bool
    {
        // Seuls les admins peuvent rejeter
        if (!$this->hasRole($currentUser, 'ROLE_ADMIN')) {
            return false;
        }

        // La demande doit être en attente
        return $request->getStatus() === ProfileUpdateRequest::STATUS_PENDING;
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
