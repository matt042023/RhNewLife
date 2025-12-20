<?php

namespace App\Service;

use App\Entity\CompteurJoursAnnuels;
use App\Entity\Contract;
use App\Entity\User;
use App\Repository\CompteurJoursAnnuelsRepository;
use App\Repository\ContractRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Service de gestion des compteurs de jours annuels pour les éducateurs
 */
class AnnualDayCounterService
{
    public const FULL_YEAR_DAYS = 258;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private CompteurJoursAnnuelsRepository $compteurRepository,
        private ContractRepository $contractRepository,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Initialise un compteur pour un contrat donné
     */
    public function initializeCounterForContract(Contract $contract): ?CompteurJoursAnnuels
    {
        if (!$contract->usesAnnualDaySystem()) {
            $this->logger->warning('Tentative d\'initialisation d\'un compteur pour un contrat qui n\'utilise pas le système annuel', [
                'contract_id' => $contract->getId(),
            ]);
            return null;
        }

        $user = $contract->getUser();
        $hireDate = $contract->getStartDate();
        $year = (int)$hireDate->format('Y');

        // Vérifier si le compteur existe déjà
        $existingCounter = $this->compteurRepository->findByUserAndYear($user, $year);
        if ($existingCounter) {
            $this->logger->info('Compteur déjà existant pour cet utilisateur et cette année', [
                'user_id' => $user->getId(),
                'year' => $year,
            ]);
            return $existingCounter;
        }

        // Créer le nouveau compteur
        $compteur = new CompteurJoursAnnuels();
        $compteur->setUser($user);
        $compteur->setYear($year);
        $compteur->setDateEmbauche($hireDate);

        // Toujours 258 jours, pas de prorata
        $compteur->setJoursAlloues((string)self::FULL_YEAR_DAYS);
        $compteur->setJoursConsommes('0.00');

        $this->entityManager->persist($compteur);
        $this->entityManager->flush();

        $this->logger->info('Compteur annuel créé', [
            'user_id' => $user->getId(),
            'year' => $year,
            'jours_alloues' => self::FULL_YEAR_DAYS,
        ]);

        return $compteur;
    }

    /**
     * Récupère le compteur actuel d'un utilisateur (année en cours)
     */
    public function getCurrentCounter(User $user): ?CompteurJoursAnnuels
    {
        return $this->compteurRepository->findCurrentCounter($user);
    }

    /**
     * Récupère le compteur d'un utilisateur pour une année donnée
     */
    public function getCounterByYear(User $user, int $year): ?CompteurJoursAnnuels
    {
        return $this->compteurRepository->findByUserAndYear($user, $year);
    }

    /**
     * Décrémente le compteur (appelé lors de l'affectation d'un éducateur)
     */
    public function decrementCounter(User $user, int $year, float $days): void
    {
        $compteur = $this->compteurRepository->findByUserAndYear($user, $year);

        if (!$compteur) {
            throw new \RuntimeException("Aucun compteur trouvé pour l'utilisateur {$user->getId()} et l'année {$year}");
        }

        $currentConsumed = (float)$compteur->getJoursConsommes();
        $newConsumed = $currentConsumed + $days;

        $compteur->setJoursConsommes((string)$newConsumed);
        $this->entityManager->flush();

        $this->logger->info('Compteur décrémenté', [
            'user_id' => $user->getId(),
            'year' => $year,
            'days_added' => $days,
            'new_consumed' => $newConsumed,
            'remaining' => $compteur->getJoursRestants(),
        ]);
    }

    /**
     * Incrémente le compteur (appelé lors de l'annulation d'une affectation)
     */
    public function incrementCounter(User $user, int $year, float $days): void
    {
        $compteur = $this->compteurRepository->findByUserAndYear($user, $year);

        if (!$compteur) {
            throw new \RuntimeException("Aucun compteur trouvé pour l'utilisateur {$user->getId()} et l'année {$year}");
        }

        $currentConsumed = (float)$compteur->getJoursConsommes();
        $newConsumed = max(0, $currentConsumed - $days); // Ne peut pas être négatif

        $compteur->setJoursConsommes((string)$newConsumed);
        $this->entityManager->flush();

        $this->logger->info('Compteur incrémenté (annulation)', [
            'user_id' => $user->getId(),
            'year' => $year,
            'days_removed' => $days,
            'new_consumed' => $newConsumed,
            'remaining' => $compteur->getJoursRestants(),
        ]);
    }

    /**
     * Reset tous les compteurs pour une nouvelle année
     * Appelé par la commande cron au 1er janvier
     */
    public function resetCountersForNewYear(int $year): array
    {
        $results = [
            'created' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        // Trouver tous les contrats actifs utilisant le système annuel
        $activeContracts = $this->contractRepository->createQueryBuilder('c')
            ->where('c.status = :status')
            ->andWhere('c.useAnnualDaySystem = :true')
            ->setParameter('status', Contract::STATUS_ACTIVE)
            ->setParameter('true', true)
            ->getQuery()
            ->getResult();

        foreach ($activeContracts as $contract) {
            try {
                $user = $contract->getUser();

                // Vérifier si le compteur existe déjà pour cette année
                $existingCounter = $this->compteurRepository->findByUserAndYear($user, $year);
                if ($existingCounter) {
                    $results['skipped']++;
                    continue;
                }

                // Créer le nouveau compteur
                $compteur = new CompteurJoursAnnuels();
                $compteur->setUser($user);
                $compteur->setYear($year);
                $compteur->setDateEmbauche($contract->getStartDate());

                // Toujours 258 jours, pas de prorata
                $compteur->setJoursAlloues((string)self::FULL_YEAR_DAYS);
                $compteur->setJoursConsommes('0.00');

                $this->entityManager->persist($compteur);
                $results['created']++;

            } catch (\Exception $e) {
                $results['errors'][] = [
                    'contract_id' => $contract->getId(),
                    'user_id' => $contract->getUser()->getId(),
                    'error' => $e->getMessage(),
                ];
                $this->logger->error('Erreur lors du reset du compteur', [
                    'contract_id' => $contract->getId(),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->entityManager->flush();

        $this->logger->info('Reset des compteurs annuels terminé', [
            'year' => $year,
            'created' => $results['created'],
            'skipped' => $results['skipped'],
            'errors_count' => count($results['errors']),
        ]);

        return $results;
    }

    /**
     * Vérifie si un utilisateur a un solde suffisant pour une affectation
     */
    public function hasSufficientBalance(User $user, int $year, float $daysRequired): bool
    {
        $compteur = $this->compteurRepository->findByUserAndYear($user, $year);

        if (!$compteur) {
            return false;
        }

        return $compteur->hasSufficientBalance($daysRequired);
    }

    /**
     * Récupère tous les compteurs avec un solde faible
     */
    public function getLowBalanceCounters(int $threshold = 10, ?int $year = null): array
    {
        return $this->compteurRepository->findCountersNeedingAlert($threshold, $year);
    }

    /**
     * Récupère tous les compteurs en solde négatif
     */
    public function getNegativeCounters(?int $year = null): array
    {
        return $this->compteurRepository->findNegativeCounters($year);
    }
}
