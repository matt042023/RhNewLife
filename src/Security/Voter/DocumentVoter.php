<?php

namespace App\Security\Voter;

use App\Entity\Document;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class DocumentVoter extends Voter
{
    public const VIEW = 'DOCUMENT_VIEW';
    public const UPLOAD = 'DOCUMENT_UPLOAD';
    public const DELETE = 'DOCUMENT_DELETE';
    public const VALIDATE = 'DOCUMENT_VALIDATE';
    public const DOWNLOAD = 'DOCUMENT_DOWNLOAD';

    protected function supports(string $attribute, mixed $subject): bool
    {
        // Upload : le subject est un User
        if ($attribute === self::UPLOAD) {
            return $subject instanceof User;
        }

        // Autres actions : subject est un Document
        return in_array($attribute, [self::VIEW, self::DELETE, self::VALIDATE, self::DOWNLOAD])
            && $subject instanceof Document;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $currentUser = $token->getUser();

        if (!$currentUser instanceof User) {
            return false;
        }

        return match ($attribute) {
            self::UPLOAD => $this->canUpload($currentUser, $subject),
            self::VIEW => $this->canView($currentUser, $subject),
            self::DELETE => $this->canDelete($currentUser, $subject),
            self::VALIDATE => $this->canValidate($currentUser),
            self::DOWNLOAD => $this->canDownload($currentUser, $subject),
            default => false,
        };
    }

    private function canUpload(User $currentUser, User $targetUser): bool
    {
        // Un utilisateur peut uploader ses propres documents
        if ($currentUser->getId() === $targetUser->getId()) {
            return true;
        }

        // Les admins peuvent uploader pour n'importe quel utilisateur
        return $this->hasRole($currentUser, ['ROLE_ADMIN']);
    }

    private function canView(User $currentUser, Document $document): bool
    {
        // L'utilisateur peut voir ses propres documents
        if ($document->getUser()->getId() === $currentUser->getId()) {
            return true;
        }

        // Les admins et directeurs peuvent voir tous les documents
        return $this->hasRole($currentUser, ['ROLE_ADMIN', 'ROLE_DIRECTOR']);
    }

    private function canDelete(User $currentUser, Document $document): bool
    {
        // L'utilisateur peut supprimer ses propres documents non validés
        if ($document->getUser()->getId() === $currentUser->getId()) {
            return $document->getStatus() !== Document::STATUS_VALIDATED;
        }

        // Les admins peuvent supprimer n'importe quel document
        return $this->hasRole($currentUser, ['ROLE_ADMIN']);
    }

    private function canValidate(User $currentUser): bool
    {
        // Seuls les admins peuvent valider des documents
        return $this->hasRole($currentUser, ['ROLE_ADMIN']);
    }

    private function canDownload(User $currentUser, Document $document): bool
    {
        // Même règles que VIEW
        return $this->canView($currentUser, $document);
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
