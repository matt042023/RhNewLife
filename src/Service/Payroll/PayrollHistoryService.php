<?php

namespace App\Service\Payroll;

use App\Entity\ConsolidationPaie;
use App\Entity\ConsolidationPaieHistory;
use App\Entity\User;
use App\Repository\ConsolidationPaieHistoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Service de gestion de l'historique des consolidations de paie
 */
class PayrollHistoryService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ConsolidationPaieHistoryRepository $historyRepository,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Enregistre une action dans l'historique
     */
    public function log(
        ConsolidationPaie $consolidation,
        string $action,
        User $user,
        ?string $field = null,
        mixed $oldValue = null,
        mixed $newValue = null,
        ?string $comment = null
    ): ConsolidationPaieHistory {
        $history = ConsolidationPaieHistory::create(
            $consolidation,
            $action,
            $user,
            $field,
            $oldValue,
            $newValue,
            $comment
        );

        $this->entityManager->persist($history);
        $this->entityManager->flush();

        $this->logger->info('Historique paie enregistré', [
            'consolidation_id' => $consolidation->getId(),
            'action' => $action,
            'field' => $field,
            'user_id' => $user->getId(),
        ]);

        return $history;
    }

    /**
     * Enregistre la création d'une consolidation
     */
    public function logCreation(ConsolidationPaie $consolidation, User $admin): ConsolidationPaieHistory
    {
        return $this->log(
            $consolidation,
            ConsolidationPaieHistory::ACTION_CREATED,
            $admin,
            null,
            null,
            null,
            'Consolidation créée'
        );
    }

    /**
     * Enregistre une modification de champ
     */
    public function logUpdate(
        ConsolidationPaie $consolidation,
        string $field,
        mixed $oldValue,
        mixed $newValue,
        User $admin,
        ?string $comment = null
    ): ConsolidationPaieHistory {
        return $this->log(
            $consolidation,
            ConsolidationPaieHistory::ACTION_UPDATED,
            $admin,
            $field,
            $oldValue,
            $newValue,
            $comment
        );
    }

    /**
     * Enregistre la validation d'une consolidation
     */
    public function logValidation(ConsolidationPaie $consolidation, User $admin): ConsolidationPaieHistory
    {
        return $this->log(
            $consolidation,
            ConsolidationPaieHistory::ACTION_VALIDATED,
            $admin,
            'status',
            ConsolidationPaie::STATUS_DRAFT,
            ConsolidationPaie::STATUS_VALIDATED,
            'Rapport validé'
        );
    }

    /**
     * Enregistre l'export d'une consolidation
     */
    public function logExport(ConsolidationPaie $consolidation, User $admin): ConsolidationPaieHistory
    {
        return $this->log(
            $consolidation,
            ConsolidationPaieHistory::ACTION_EXPORTED,
            $admin,
            null,
            null,
            null,
            'Export généré'
        );
    }

    /**
     * Enregistre une correction après validation
     */
    public function logCorrection(
        ConsolidationPaie $consolidation,
        string $field,
        mixed $oldValue,
        mixed $newValue,
        User $admin,
        ?string $comment = null
    ): ConsolidationPaieHistory {
        return $this->log(
            $consolidation,
            ConsolidationPaieHistory::ACTION_CORRECTION,
            $admin,
            $field,
            $oldValue,
            $newValue,
            $comment ?? 'Correction après validation'
        );
    }

    /**
     * Enregistre la réouverture d'une consolidation
     */
    public function logReopening(
        ConsolidationPaie $consolidation,
        User $admin,
        string $reason
    ): ConsolidationPaieHistory {
        return $this->log(
            $consolidation,
            ConsolidationPaieHistory::ACTION_REOPENED,
            $admin,
            'status',
            $consolidation->getStatus(),
            ConsolidationPaie::STATUS_DRAFT,
            $reason
        );
    }

    /**
     * Récupère l'historique complet d'une consolidation
     *
     * @return ConsolidationPaieHistory[]
     */
    public function getHistory(ConsolidationPaie $consolidation): array
    {
        return $this->historyRepository->findByConsolidation($consolidation);
    }

    /**
     * Récupère les corrections d'une consolidation
     *
     * @return ConsolidationPaieHistory[]
     */
    public function getCorrections(ConsolidationPaie $consolidation): array
    {
        return $this->historyRepository->findCorrections($consolidation);
    }

    /**
     * Compte les actions par type pour une consolidation
     *
     * @return array<string, int>
     */
    public function countActions(ConsolidationPaie $consolidation): array
    {
        return $this->historyRepository->countByAction($consolidation);
    }

    /**
     * Vérifie si une consolidation a été corrigée après validation
     */
    public function hasCorrections(ConsolidationPaie $consolidation): bool
    {
        $corrections = $this->getCorrections($consolidation);
        return count($corrections) > 0;
    }

    /**
     * Retourne les dernières modifications globales (pour tableau de bord admin)
     *
     * @return ConsolidationPaieHistory[]
     */
    public function getLatestActivity(int $limit = 20): array
    {
        return $this->historyRepository->findLatest($limit);
    }

    /**
     * Génère un résumé lisible de l'historique pour affichage
     */
    public function formatHistoryEntry(ConsolidationPaieHistory $entry): array
    {
        $data = [
            'id' => $entry->getId(),
            'action' => $entry->getAction(),
            'action_label' => $entry->getActionLabel(),
            'field' => $entry->getField(),
            'field_label' => $entry->getFieldLabel(),
            'old_value' => $entry->getOldValueDecoded(),
            'new_value' => $entry->getNewValueDecoded(),
            'modified_by' => $entry->getModifiedBy()?->getFullName(),
            'modified_at' => $entry->getModifiedAt()?->format('d/m/Y H:i'),
            'comment' => $entry->getComment(),
        ];

        // Générer une description lisible
        $description = match ($entry->getAction()) {
            ConsolidationPaieHistory::ACTION_CREATED => 'Consolidation créée',
            ConsolidationPaieHistory::ACTION_VALIDATED => 'Rapport validé',
            ConsolidationPaieHistory::ACTION_EXPORTED => 'Export généré',
            ConsolidationPaieHistory::ACTION_REOPENED => 'Rapport réouvert',
            ConsolidationPaieHistory::ACTION_CORRECTION => sprintf(
                'Correction de "%s" : %s → %s',
                $entry->getFieldLabel(),
                $this->formatValue($entry->getOldValueDecoded()),
                $this->formatValue($entry->getNewValueDecoded())
            ),
            ConsolidationPaieHistory::ACTION_UPDATED => sprintf(
                'Modification de "%s" : %s → %s',
                $entry->getFieldLabel(),
                $this->formatValue($entry->getOldValueDecoded()),
                $this->formatValue($entry->getNewValueDecoded())
            ),
            default => $entry->getActionLabel(),
        };

        $data['description'] = $description;

        return $data;
    }

    /**
     * Formate une valeur pour affichage
     */
    private function formatValue(mixed $value): string
    {
        if ($value === null) {
            return '(vide)';
        }

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        if (is_bool($value)) {
            return $value ? 'Oui' : 'Non';
        }

        return (string) $value;
    }
}
