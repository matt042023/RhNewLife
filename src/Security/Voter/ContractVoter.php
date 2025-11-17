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
    public const SEND_FOR_SIGNATURE = 'CONTRACT_SEND_FOR_SIGNATURE';
    public const RESEND_SIGNATURE = 'CONTRACT_RESEND_SIGNATURE';
    public const VALIDATE_SIGNED = 'CONTRACT_VALIDATE_SIGNED';
    public const SEND_TO_ACCOUNTING = 'CONTRACT_SEND_TO_ACCOUNTING';
    public const UPLOAD_SIGNED = 'CONTRACT_UPLOAD_SIGNED';
    public const CREATE_AMENDMENT = 'CONTRACT_CREATE_AMENDMENT';
    public const CLOSE = 'CONTRACT_CLOSE';
    public const CANCEL = 'CONTRACT_CANCEL';

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
            self::SEND_FOR_SIGNATURE,
            self::RESEND_SIGNATURE,
            self::VALIDATE_SIGNED,
            self::SEND_TO_ACCOUNTING,
            self::UPLOAD_SIGNED,
            self::CREATE_AMENDMENT,
            self::CLOSE,
            self::CANCEL,
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
            self::SEND_FOR_SIGNATURE => $this->canSendForSignature($currentUser, $subject),
            self::RESEND_SIGNATURE => $this->canResendSignature($currentUser, $subject),
            self::VALIDATE_SIGNED => $this->canValidateSigned($currentUser, $subject),
            self::SEND_TO_ACCOUNTING => $this->canSendToAccounting($currentUser, $subject),
            self::UPLOAD_SIGNED => $this->canUploadSigned($currentUser, $subject),
            self::CREATE_AMENDMENT => $this->canCreateAmendment($currentUser, $subject),
            self::CLOSE => $this->canClose($currentUser, $subject),
            self::CANCEL => $this->canCancel($currentUser, $subject),
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

        // Le contrat doit être validé (active ou signé en attente de validation)
        return in_array($contract->getStatus(), [
            Contract::STATUS_ACTIVE,
            Contract::STATUS_SIGNED_PENDING_VALIDATION,
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

        // Le contrat doit être actif ou signé en attente de validation
        return in_array($contract->getStatus(), [
            Contract::STATUS_ACTIVE,
            Contract::STATUS_SIGNED_PENDING_VALIDATION,
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

    private function canSendForSignature(User $currentUser, Contract $contract): bool
    {
        // Seuls les admins peuvent envoyer pour signature
        if (!$this->hasRole($currentUser, 'ROLE_ADMIN')) {
            return false;
        }

        // Ne peut envoyer que les contrats en brouillon
        return $contract->getStatus() === Contract::STATUS_DRAFT;
    }

    private function canResendSignature(User $currentUser, Contract $contract): bool
    {
        // Seuls les admins peuvent renvoyer le lien de signature
        if (!$this->hasRole($currentUser, 'ROLE_ADMIN')) {
            return false;
        }

        // Ne peut renvoyer que si le contrat est en attente de signature
        return $contract->getStatus() === Contract::STATUS_PENDING_SIGNATURE;
    }

    private function canValidateSigned(User $currentUser, Contract $contract): bool
    {
        // Seuls les admins peuvent valider les contrats signés
        if (!$this->hasRole($currentUser, 'ROLE_ADMIN')) {
            return false;
        }

        // Ne peut valider que les contrats signés en attente de validation
        return $contract->getStatus() === Contract::STATUS_SIGNED_PENDING_VALIDATION;
    }

    private function canCancel(User $currentUser, Contract $contract): bool
    {
        // Seuls les admins peuvent annuler les contrats
        if (!$this->hasRole($currentUser, 'ROLE_ADMIN')) {
            return false;
        }

        // Ne peut annuler que les contrats en brouillon ou en attente de signature
        return in_array($contract->getStatus(), [
            Contract::STATUS_DRAFT,
            Contract::STATUS_PENDING_SIGNATURE,
        ]);
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
