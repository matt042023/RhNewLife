<?php

namespace App\Security\Voter;

use App\Entity\Document;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class DocumentVoter extends Voter
{
    public const VIEW = 'DOCUMENT_VIEW';
    public const VIEW_ARCHIVED = 'DOCUMENT_VIEW_ARCHIVED';
    public const UPLOAD = 'DOCUMENT_UPLOAD';
    public const DELETE = 'DOCUMENT_DELETE';
    public const VALIDATE = 'DOCUMENT_VALIDATE';
    public const DOWNLOAD = 'DOCUMENT_DOWNLOAD';
    public const ARCHIVE = 'DOCUMENT_ARCHIVE';
    public const RESTORE = 'DOCUMENT_RESTORE';
    public const REPLACE = 'DOCUMENT_REPLACE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        if ($attribute === self::UPLOAD) {
            return $subject instanceof User;
        }

        return in_array($attribute, [
            self::VIEW,
            self::VIEW_ARCHIVED,
            self::DELETE,
            self::VALIDATE,
            self::DOWNLOAD,
            self::ARCHIVE,
            self::RESTORE,
            self::REPLACE,
        ], true) && $subject instanceof Document;
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
            self::VIEW_ARCHIVED => $this->canViewArchived($currentUser, $subject),
            self::DELETE => $this->canDelete($currentUser, $subject),
            self::VALIDATE => $this->canValidate($currentUser),
            self::DOWNLOAD => $this->canDownload($currentUser, $subject),
            self::ARCHIVE => $this->canArchive($currentUser, $subject),
            self::RESTORE => $this->canRestore($currentUser, $subject),
            self::REPLACE => $this->canReplace($currentUser, $subject),
            default => false,
        };
    }

    private function canUpload(User $currentUser, User $targetUser): bool
    {
        if ($this->isSameUser($currentUser, $targetUser)) {
            return true;
        }

        return $this->hasRole($currentUser, ['ROLE_ADMIN']);
    }

    private function canView(User $currentUser, Document $document): bool
    {
        if ($document->isArchived()) {
            return false;
        }

        if ($this->isOwner($currentUser, $document)) {
            return true;
        }

        return $this->canAccessAsManager($currentUser, $document);
    }

    private function canViewArchived(User $currentUser, Document $document): bool
    {
        if (!$document->isArchived()) {
            return false;
        }

        return $this->canAccessAsManager($currentUser, $document);
    }

    private function canDownload(User $currentUser, Document $document): bool
    {
        return $this->canView($currentUser, $document);
    }

    private function canDelete(User $currentUser, Document $document): bool
    {
        if (!$this->hasRole($currentUser, ['ROLE_ADMIN'])) {
            return false;
        }

        if ($document->isArchived()) {
            return false;
        }

        return $document->getStatus() !== Document::STATUS_VALIDATED;
    }

    private function canValidate(User $currentUser): bool
    {
        return $this->hasRole($currentUser, ['ROLE_ADMIN']);
    }

    private function canArchive(User $currentUser, Document $document): bool
    {
        if (!$this->hasRole($currentUser, ['ROLE_ADMIN'])) {
            return false;
        }

        return !$document->isArchived();
    }

    private function canRestore(User $currentUser, Document $document): bool
    {
        if (!$this->hasRole($currentUser, ['ROLE_ADMIN'])) {
            return false;
        }

        return $document->isArchived();
    }

    private function canReplace(User $currentUser, Document $document): bool
    {
        if ($document->isArchived()) {
            return false;
        }

        if ($this->hasRole($currentUser, ['ROLE_ADMIN'])) {
            return true;
        }

        if (!$this->isOwner($currentUser, $document)) {
            return false;
        }

        return $document->getStatus() !== Document::STATUS_VALIDATED;
    }

    private function canAccessAsManager(User $currentUser, Document $document): bool
    {
        $type = $document->getType();

        if (in_array($type, [Document::TYPE_RIB, Document::TYPE_MEDICAL_CERTIFICATE], true)) {
            return $this->hasRole($currentUser, ['ROLE_ADMIN']);
        }

        if ($type === Document::TYPE_PAYSLIP) {
            if ($this->isOwner($currentUser, $document)) {
                return true;
            }

            return $this->hasRole($currentUser, ['ROLE_ADMIN']);
        }

        return $this->hasRole($currentUser, ['ROLE_ADMIN', 'ROLE_DIRECTOR']);
    }

    private function isOwner(User $user, Document $document): bool
    {
        $documentUser = $document->getUser();

        if (!$documentUser instanceof User) {
            return false;
        }

        if ($documentUser === $user) {
            return true;
        }

        $documentUserId = $documentUser->getId();
        $userId = $user->getId();

        return $documentUserId !== null && $documentUserId === $userId;
    }

    private function isSameUser(User $a, User $b): bool
    {
        if ($a === $b) {
            return true;
        }

        $aId = $a->getId();
        $bId = $b->getId();

        return $aId !== null && $bId !== null && $aId === $bId;
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
