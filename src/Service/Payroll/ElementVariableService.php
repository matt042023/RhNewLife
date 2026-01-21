<?php

namespace App\Service\Payroll;

use App\Entity\ConsolidationPaie;
use App\Entity\ElementVariable;
use App\Entity\User;
use App\Repository\ConsolidationPaieRepository;
use App\Repository\ElementVariableRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Service de gestion des éléments variables de paie
 */
class ElementVariableService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ElementVariableRepository $elementVariableRepository,
        private ConsolidationPaieRepository $consolidationRepository,
        private PayrollHistoryService $historyService,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Crée un nouvel élément variable
     */
    public function create(
        User $user,
        string $period,
        string $category,
        string $label,
        string $amount,
        ?string $description = null,
        ?User $admin = null
    ): ElementVariable {
        $element = new ElementVariable();
        $element->setUser($user);
        $element->setPeriod($period);
        $element->setCategory($category);
        $element->setLabel($label);
        $element->setAmount($amount);
        $element->setDescription($description);

        // Lier à la consolidation si elle existe
        $consolidation = $this->consolidationRepository->findByUserAndPeriod($user, $period);
        if ($consolidation) {
            $consolidation->addElementVariable($element);
        }

        $this->entityManager->persist($element);
        $this->entityManager->flush();

        // Recalculer le total des variables dans la consolidation
        if ($consolidation) {
            $consolidation->recalculateTotalVariables();
            $this->entityManager->flush();

            // Log dans l'historique de la consolidation
            if ($admin) {
                $this->historyService->logUpdate(
                    $consolidation,
                    'totalVariables',
                    null,
                    $consolidation->getTotalVariables(),
                    $admin,
                    sprintf('Ajout élément variable: %s (%s)', $label, $category)
                );
            }
        }

        $this->logger->info('Élément variable créé', [
            'id' => $element->getId(),
            'user_id' => $user->getId(),
            'period' => $period,
            'category' => $category,
            'amount' => $amount,
        ]);

        return $element;
    }

    /**
     * Met à jour un élément variable
     */
    public function update(
        ElementVariable $element,
        string $category,
        string $label,
        string $amount,
        ?string $description,
        User $admin
    ): ElementVariable {
        $oldAmount = $element->getAmount();
        $oldCategory = $element->getCategory();
        $oldLabel = $element->getLabel();

        $element->setCategory($category);
        $element->setLabel($label);
        $element->setAmount($amount);
        $element->setDescription($description);

        $this->entityManager->flush();

        // Recalculer le total si lié à une consolidation
        $consolidation = $element->getConsolidation();
        if ($consolidation) {
            $oldTotal = $consolidation->getTotalVariables();
            $consolidation->recalculateTotalVariables();
            $this->entityManager->flush();

            // Log dans l'historique
            if ($oldTotal !== $consolidation->getTotalVariables()) {
                $this->historyService->logUpdate(
                    $consolidation,
                    'totalVariables',
                    $oldTotal,
                    $consolidation->getTotalVariables(),
                    $admin,
                    sprintf('Modification élément variable: %s → %s', $oldLabel, $label)
                );
            }
        }

        $this->logger->info('Élément variable mis à jour', [
            'id' => $element->getId(),
            'old_amount' => $oldAmount,
            'new_amount' => $amount,
        ]);

        return $element;
    }

    /**
     * Supprime un élément variable
     */
    public function delete(ElementVariable $element, User $admin): void
    {
        $consolidation = $element->getConsolidation();
        $label = $element->getLabel();
        $amount = $element->getAmount();

        $this->entityManager->remove($element);
        $this->entityManager->flush();

        // Recalculer le total si lié à une consolidation
        if ($consolidation) {
            $oldTotal = $consolidation->getTotalVariables();
            $consolidation->recalculateTotalVariables();
            $this->entityManager->flush();

            // Log dans l'historique
            $this->historyService->logUpdate(
                $consolidation,
                'totalVariables',
                $oldTotal,
                $consolidation->getTotalVariables(),
                $admin,
                sprintf('Suppression élément variable: %s (%s€)', $label, $amount)
            );
        }

        $this->logger->info('Élément variable supprimé', [
            'label' => $label,
            'amount' => $amount,
        ]);
    }

    /**
     * Valide un élément variable
     */
    public function validate(ElementVariable $element, User $admin): ElementVariable
    {
        if ($element->isValidated()) {
            return $element;
        }

        $element->validate($admin);
        $this->entityManager->flush();

        $this->logger->info('Élément variable validé', [
            'id' => $element->getId(),
            'admin_id' => $admin->getId(),
        ]);

        return $element;
    }

    /**
     * Valide tous les éléments d'une consolidation
     */
    public function validateAllForConsolidation(ConsolidationPaie $consolidation, User $admin): int
    {
        $count = 0;
        foreach ($consolidation->getElementsVariables() as $element) {
            if ($element->isDraft()) {
                $element->validate($admin);
                $count++;
            }
        }

        if ($count > 0) {
            $this->entityManager->flush();

            $this->logger->info('Éléments variables validés en masse', [
                'consolidation_id' => $consolidation->getId(),
                'count' => $count,
            ]);
        }

        return $count;
    }

    /**
     * Récupère les éléments variables pour un utilisateur et une période
     *
     * @return ElementVariable[]
     */
    public function getByUserAndPeriod(User $user, string $period): array
    {
        return $this->elementVariableRepository->findBy(
            ['user' => $user, 'period' => $period],
            ['category' => 'ASC', 'createdAt' => 'ASC']
        );
    }

    /**
     * Récupère les éléments variables pour une consolidation
     *
     * @return ElementVariable[]
     */
    public function getByConsolidation(ConsolidationPaie $consolidation): array
    {
        return $this->elementVariableRepository->findBy(
            ['consolidation' => $consolidation],
            ['category' => 'ASC', 'createdAt' => 'ASC']
        );
    }

    /**
     * Calcule le total des éléments variables pour un utilisateur et une période
     */
    public function calculateTotal(User $user, string $period): float
    {
        $elements = $this->getByUserAndPeriod($user, $period);
        $total = 0;

        foreach ($elements as $element) {
            $total += (float) $element->getAmount();
        }

        return $total;
    }

    /**
     * Calcule les totaux par catégorie
     *
     * @return array<string, float>
     */
    public function calculateTotalsByCategory(User $user, string $period): array
    {
        $elements = $this->getByUserAndPeriod($user, $period);
        $totals = [];

        foreach (ElementVariable::CATEGORIES as $code => $label) {
            $totals[$code] = 0;
        }

        foreach ($elements as $element) {
            $category = $element->getCategory();
            $totals[$category] = ($totals[$category] ?? 0) + (float) $element->getAmount();
        }

        return $totals;
    }

    /**
     * Lie les éléments variables orphelins à une consolidation
     */
    public function linkToConsolidation(ConsolidationPaie $consolidation): int
    {
        $user = $consolidation->getUser();
        $period = $consolidation->getPeriod();

        if (!$user || !$period) {
            return 0;
        }

        $orphans = $this->elementVariableRepository->findBy([
            'user' => $user,
            'period' => $period,
            'consolidation' => null,
        ]);

        foreach ($orphans as $element) {
            $consolidation->addElementVariable($element);
        }

        if (count($orphans) > 0) {
            $this->entityManager->flush();
            $consolidation->recalculateTotalVariables();
            $this->entityManager->flush();
        }

        return count($orphans);
    }

    /**
     * Copie les éléments récurrents d'un mois précédent
     *
     * @return ElementVariable[]
     */
    public function copyRecurringFromPreviousMonth(User $user, string $targetPeriod, User $admin): array
    {
        // Calculer le mois précédent
        $date = new \DateTime($targetPeriod . '-01');
        $date->modify('-1 month');
        $previousPeriod = $date->format('Y-m');

        // Récupérer les éléments du mois précédent qui sont des primes régulières
        $previousElements = $this->elementVariableRepository->findBy([
            'user' => $user,
            'period' => $previousPeriod,
            'category' => ElementVariable::CATEGORY_PRIME,
        ]);

        $copiedElements = [];
        foreach ($previousElements as $element) {
            $newElement = $this->create(
                $user,
                $targetPeriod,
                $element->getCategory(),
                $element->getLabel(),
                $element->getAmount(),
                $element->getDescription(),
                $admin
            );
            $copiedElements[] = $newElement;
        }

        if (count($copiedElements) > 0) {
            $this->logger->info('Éléments récurrents copiés', [
                'user_id' => $user->getId(),
                'from' => $previousPeriod,
                'to' => $targetPeriod,
                'count' => count($copiedElements),
            ]);
        }

        return $copiedElements;
    }

    /**
     * Retourne les catégories disponibles
     *
     * @return array<string, string>
     */
    public function getCategories(): array
    {
        return ElementVariable::CATEGORIES;
    }
}
