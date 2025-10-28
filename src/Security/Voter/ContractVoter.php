<?php

namespace App\Security\Voter;

use App\Entity\Contract;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class ContractVoter extends Voter
{
    public const CREATE = 'CONTRACT_CREATE';
    public const VIEW = 'CONTRACT_VIEW';
    public const EDIT = 'CONTRACT_EDIT';
    public const VALIDATE = 'CONTRACT_VALIDATE';
    public const SEND_TO_ACCOUNTING = 'CONTRACT_SEND_TO_ACCOUNTING';
    public const UPLOAD_SIGNED = 'CONTRACT_UPLOAD_SIGNED';
    public const CREATE_AMENDMENT = 'CONTRACT_CREATE_AMENDMENT';
    public const CLOSE = 'CONTRACT_CLOSE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        // CREATE prend un User comme subject
        if ($attribute === self::CREATE) {
            return $subject instanceof User;
        }

        // Autres actions prennent un Contract
        return in_array($attribute, [
            self::VIEW,
            self::EDIT,
            self::VALIDATE,
            self::SEND_TO_ACCOUNTING,
            self::UPLOAD_SIGNED,
            self::CREATE_AMENDMENT,
            self::CLOSE,
        ]) && $subject instanceof Contract;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $currentUser = $token->getUser();

        if (!$currentUser instanceof User) {
            return false;
        }

        return match ($attribute) {
            self::CREATE => $this->canCreate($currentUser, $subject),
            self::VIEW => $this->canView($currentUser, $subject),
            self::EDIT => $this->canEdit($currentUser, $subject),
            self::VALIDATE => $this->canValidate($currentUser, $subject),
            self::SEND_TO_ACCOUNTING => $this->canSendToAccounting($currentUser, $subject),
            self::UPLOAD_SIGNED => $this->canUploadSigned($currentUser, $subject),
            self::CREATE_AMENDMENT => $this->canCreateAmendment($currentUser, $subject),
            self::CLOSE => $this->canClose($currentUser, $subject),
            default => false,
        };
    }

    private function canCreate(User $currentUser, User $targetUser): bool
    {
        // Seuls les admins peuvent créer des contrats
        if (!$this->hasRole($currentUser, 'ROLE_ADMIN')) {
            return false;
        }

        // L'utilisateur cible doit être actif ou en onboarding
        return in_array($targetUser->getStatus(), [
            User::STATUS_ACTIVE,
            User::STATUS_ONBOARDING,
            User::STATUS_INVITED,
        ]);
    }

    private function canView(User $currentUser, Contract $contract): bool
    {
        // Un utilisateur peut voir ses propres contrats
        if ($contract->getUser()->getId() === $currentUser->getId()) {
            return true;
        }

        // Les admins et directeurs peuvent voir tous les contrats
        return $this->hasRole($currentUser, ['ROLE_ADMIN', 'ROLE_DIRECTOR']);
    }

    private function canEdit(User $currentUser, Contract $contract): bool
    {
        // Seuls les admins peuvent modifier les contrats
        if (!$this->hasRole($currentUser, 'ROLE_ADMIN')) {
            return false;
        }

        // Ne peut modifier que les contrats en brouillon
        return $contract->getStatus() === Contract::STATUS_DRAFT;
    }

    private function canValidate(User $currentUser, Contract $contract): bool
    {
        // Seuls les admins peuvent valider les contrats
        if (!$this->hasRole($currentUser, 'ROLE_ADMIN')) {
            return false;
        }

        // Ne peut valider que les contrats en brouillon
        return $contract->getStatus() === Contract::STATUS_DRAFT;
    }

    private function canSendToAccounting(User $currentUser, Contract $contract): bool
    {
        // Seuls les admins peuvent envoyer au bureau comptable
        if (!$this->hasRole($currentUser, 'ROLE_ADMIN')) {
            return false;
        }

        // Le contrat doit être validé (active ou signed)
        return in_array($contract->getStatus(), [
            Contract::STATUS_ACTIVE,
            Contract::STATUS_SIGNED,
        ]);
    }

    private function canUploadSigned(User $currentUser, Contract $contract): bool
    {
        // Seuls les admins peuvent uploader les contrats signés
        if (!$this->hasRole($currentUser, 'ROLE_ADMIN')) {
            return false;
        }

        // Le contrat doit être actif (pas en brouillon, pas terminé)
        return $contract->getStatus() === Contract::STATUS_ACTIVE;
    }

    private function canCreateAmendment(User $currentUser, Contract $contract): bool
    {
        // Seuls les admins peuvent créer des avenants
        if (!$this->hasRole($currentUser, 'ROLE_ADMIN')) {
            return false;
        }

        // Le contrat doit être actif ou signé
        return in_array($contract->getStatus(), [
            Contract::STATUS_ACTIVE,
            Contract::STATUS_SIGNED,
        ]);
    }

    private function canClose(User $currentUser, Contract $contract): bool
    {
        // Seuls les admins peuvent clôturer les contrats
        if (!$this->hasRole($currentUser, 'ROLE_ADMIN')) {
            return false;
        }

        // Ne peut pas clôturer un contrat déjà terminé
        return $contract->getStatus() !== Contract::STATUS_TERMINATED;
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
