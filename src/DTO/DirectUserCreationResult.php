<?php

namespace App\DTO;

use App\Entity\Contract;
use App\Entity\Document;
use App\Entity\User;

class DirectUserCreationResult
{
    /**
     * @param User $user L'utilisateur créé
     * @param Document[] $documents Les documents uploadés
     * @param Contract|null $contract Le contrat créé (si demandé)
     * @param bool $activationEmailSent Si l'email d'activation a été envoyé
     * @param string[] $warnings Les avertissements (erreurs non bloquantes)
     */
    public function __construct(
        private readonly User $user,
        private readonly array $documents = [],
        private readonly ?Contract $contract = null,
        private readonly bool $activationEmailSent = false,
        private readonly array $warnings = []
    ) {}

    public function getUser(): User
    {
        return $this->user;
    }

    /**
     * @return Document[]
     */
    public function getDocuments(): array
    {
        return $this->documents;
    }

    public function getContract(): ?Contract
    {
        return $this->contract;
    }

    public function isActivationEmailSent(): bool
    {
        return $this->activationEmailSent;
    }

    /**
     * @return string[]
     */
    public function getWarnings(): array
    {
        return $this->warnings;
    }

    public function hasWarnings(): bool
    {
        return count($this->warnings) > 0;
    }

    public function getDocumentsCount(): int
    {
        return count($this->documents);
    }

    public function hasContract(): bool
    {
        return $this->contract !== null;
    }

    public function toArray(): array
    {
        return [
            'user_id' => $this->user->getId(),
            'matricule' => $this->user->getMatricule(),
            'full_name' => $this->user->getFullName(),
            'documents_count' => $this->getDocumentsCount(),
            'has_contract' => $this->hasContract(),
            'activation_email_sent' => $this->activationEmailSent,
            'warnings' => $this->warnings,
        ];
    }
}
