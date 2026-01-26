<?php

namespace App\Service\Payroll;

use App\Entity\ConsolidationPaie;
use App\Entity\User;
use App\Repository\ConsolidationPaieRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Service de validation des rapports de paie
 */
class PayrollValidationService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ConsolidationPaieRepository $consolidationRepository,
        private PayrollHistoryService $historyService,
        private CPCounterService $cpCounterService,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Valide un rapport de paie
     */
    public function validate(ConsolidationPaie $consolidation, User $admin): ConsolidationPaie
    {
        if (!$consolidation->isDraft()) {
            throw new \InvalidArgumentException('Seuls les rapports en brouillon peuvent être validés.');
        }

        $oldStatus = $consolidation->getStatus();

        // Valider la consolidation
        $consolidation->validate($admin);

        // Créditer les CP mensuels pour l'utilisateur
        $user = $consolidation->getUser();
        $year = $consolidation->getYear();
        $month = $consolidation->getMonth();

        if ($user && $year && $month) {
            // Créditer les CP acquis mensuels (2.5j × prorata)
            $cpAcquis = $this->cpCounterService->creditMonthlyCP($user, $year, $month);

            // Note: Les CP pris sont déjà déduits lors de la validation de l'absence
            // (AbsenceService::validateAbsence -> counterService->deductDays)
            // La consolidation enregistre uniquement cpPris pour information/historique

            $this->logger->info('CP traités lors de la validation', [
                'user_id' => $user->getId(),
                'cp_acquis' => $cpAcquis,
                'cp_pris' => $consolidation->getCpPris(),
            ]);
        }

        $this->entityManager->flush();

        // Log dans l'historique
        $this->historyService->logValidation($consolidation, $admin);

        $this->logger->info('Rapport de paie validé', [
            'consolidation_id' => $consolidation->getId(),
            'admin_id' => $admin->getId(),
        ]);

        return $consolidation;
    }

    /**
     * Valide tous les rapports d'un mois
     *
     * @return int Nombre de rapports validés
     */
    public function validateMonth(int $year, int $month, User $admin): int
    {
        $period = sprintf('%04d-%02d', $year, $month);
        $drafts = $this->consolidationRepository->findByPeriodAndStatus($period, ConsolidationPaie::STATUS_DRAFT);

        $count = 0;
        foreach ($drafts as $consolidation) {
            $this->validate($consolidation, $admin);
            $count++;
        }

        $this->logger->info('Validation mensuelle effectuée', [
            'period' => $period,
            'count' => $count,
        ]);

        return $count;
    }

    /**
     * Réouvre un rapport validé pour correction
     */
    public function reopen(ConsolidationPaie $consolidation, User $admin, string $reason): ConsolidationPaie
    {
        if ($consolidation->isDraft()) {
            throw new \InvalidArgumentException('Ce rapport est déjà en brouillon.');
        }

        $oldStatus = $consolidation->getStatus();

        // Log avant la modification
        $this->historyService->logReopening($consolidation, $admin, $reason);

        // Remettre en brouillon
        $consolidation->setStatus(ConsolidationPaie::STATUS_DRAFT);
        $consolidation->setValidatedBy(null);
        $consolidation->setValidatedAt(null);

        $this->entityManager->flush();

        $this->logger->info('Rapport de paie réouvert', [
            'consolidation_id' => $consolidation->getId(),
            'old_status' => $oldStatus,
            'reason' => $reason,
        ]);

        return $consolidation;
    }

    /**
     * Marque un rapport comme exporté
     */
    public function markAsExported(ConsolidationPaie $consolidation, User $admin): ConsolidationPaie
    {
        if (!$consolidation->isValidated() && !$consolidation->isExported()) {
            throw new \InvalidArgumentException('Seuls les rapports validés peuvent être exportés.');
        }

        $consolidation->markAsExported();
        $this->entityManager->flush();

        // Log dans l'historique
        $this->historyService->logExport($consolidation, $admin);

        $this->logger->info('Rapport de paie marqué comme exporté', [
            'consolidation_id' => $consolidation->getId(),
        ]);

        return $consolidation;
    }

    /**
     * Marque un rapport comme envoyé au comptable
     */
    public function markAsSentToAccountant(ConsolidationPaie $consolidation): ConsolidationPaie
    {
        $consolidation->markAsSentToAccountant();
        $this->entityManager->flush();

        $this->logger->info('Rapport de paie envoyé au comptable', [
            'consolidation_id' => $consolidation->getId(),
        ]);

        return $consolidation;
    }

    /**
     * Effectue une correction sur un champ du rapport
     */
    public function correctField(
        ConsolidationPaie $consolidation,
        string $field,
        mixed $newValue,
        User $admin,
        ?string $comment = null
    ): ConsolidationPaie {
        // Récupérer l'ancienne valeur
        $oldValue = match ($field) {
            'joursTravailes' => $consolidation->getJoursTravailes(),
            'joursEvenements' => $consolidation->getJoursEvenements(),
            'joursAbsence' => $consolidation->getJoursAbsence(),
            'cpAcquis' => $consolidation->getCpAcquis(),
            'cpPris' => $consolidation->getCpPris(),
            'cpSoldeDebut' => $consolidation->getCpSoldeDebut(),
            'cpSoldeFin' => $consolidation->getCpSoldeFin(),
            'totalVariables' => $consolidation->getTotalVariables(),
            default => throw new \InvalidArgumentException("Champ inconnu: {$field}"),
        };

        // Appliquer la nouvelle valeur
        match ($field) {
            'joursTravailes' => $consolidation->setJoursTravailes(number_format((float) $newValue, 2, '.', '')),
            'joursEvenements' => $consolidation->setJoursEvenements(number_format((float) $newValue, 2, '.', '')),
            'joursAbsence' => $consolidation->setJoursAbsence(is_array($newValue) ? $newValue : null),
            'cpAcquis' => $consolidation->setCpAcquis(number_format((float) $newValue, 2, '.', '')),
            'cpPris' => $consolidation->setCpPris(number_format((float) $newValue, 2, '.', '')),
            'cpSoldeDebut' => $consolidation->setCpSoldeDebut(number_format((float) $newValue, 2, '.', '')),
            'cpSoldeFin' => $consolidation->setCpSoldeFin(number_format((float) $newValue, 2, '.', '')),
            'totalVariables' => $consolidation->setTotalVariables(number_format((float) $newValue, 2, '.', '')),
            default => null,
        };

        // Recalculer le solde CP fin si nécessaire
        if (in_array($field, ['cpSoldeDebut', 'cpAcquis', 'cpPris'], true)) {
            $consolidation->recalculateCpSoldeFin();
        }

        $this->entityManager->flush();

        // Log la correction (action différente si le rapport était déjà validé)
        $isPostValidation = !$consolidation->isDraft();
        if ($isPostValidation) {
            $this->historyService->logCorrection($consolidation, $field, $oldValue, $newValue, $admin, $comment);
        } else {
            $this->historyService->logUpdate($consolidation, $field, $oldValue, $newValue, $admin, $comment);
        }

        $this->logger->info('Correction effectuée sur le rapport', [
            'consolidation_id' => $consolidation->getId(),
            'field' => $field,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'post_validation' => $isPostValidation,
        ]);

        return $consolidation;
    }

    /**
     * Vérifie si un rapport peut être validé
     *
     * @return array{valid: bool, errors: string[]}
     */
    public function canValidate(ConsolidationPaie $consolidation): array
    {
        $errors = [];

        if (!$consolidation->isDraft()) {
            $errors[] = 'Le rapport n\'est pas en brouillon.';
        }

        // Vérifier que les jours travaillés sont calculés
        if ((float) $consolidation->getJoursTravailes() === 0.0 && (float) $consolidation->getJoursEvenements() === 0.0) {
            // Ce n'est pas forcément une erreur, mais un avertissement
        }

        // Vérifier que les CP sont cohérents
        $cpSoldeCalcule = (float) $consolidation->getCpSoldeDebut()
            + (float) $consolidation->getCpAcquis()
            - (float) $consolidation->getCpPris();

        if (abs($cpSoldeCalcule - (float) $consolidation->getCpSoldeFin()) > 0.01) {
            $errors[] = 'Le solde CP fin de mois ne correspond pas au calcul.';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Retourne les statistiques de validation pour un mois
     */
    public function getMonthStats(string $period): array
    {
        $counts = $this->consolidationRepository->countByStatusForPeriod($period);

        $total = array_sum($counts);
        $validated = ($counts[ConsolidationPaie::STATUS_VALIDATED] ?? 0)
            + ($counts[ConsolidationPaie::STATUS_EXPORTED] ?? 0)
            + ($counts[ConsolidationPaie::STATUS_ARCHIVED] ?? 0);

        return [
            'period' => $period,
            'total' => $total,
            'draft' => $counts[ConsolidationPaie::STATUS_DRAFT] ?? 0,
            'validated' => $validated,
            'exported' => $counts[ConsolidationPaie::STATUS_EXPORTED] ?? 0,
            'archived' => $counts[ConsolidationPaie::STATUS_ARCHIVED] ?? 0,
            'completion_rate' => $total > 0 ? round(($validated / $total) * 100, 1) : 0,
        ];
    }

    /**
     * Archive un rapport (après export et envoi comptable)
     */
    public function archive(ConsolidationPaie $consolidation): ConsolidationPaie
    {
        if (!$consolidation->isExported()) {
            throw new \InvalidArgumentException('Seuls les rapports exportés peuvent être archivés.');
        }

        $consolidation->setStatus(ConsolidationPaie::STATUS_ARCHIVED);
        $this->entityManager->flush();

        $this->logger->info('Rapport de paie archivé', [
            'consolidation_id' => $consolidation->getId(),
        ]);

        return $consolidation;
    }
}
