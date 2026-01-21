<?php

namespace App\Service;

use App\Entity\Affectation;
use App\Entity\CompteurJoursAnnuels;
use App\Entity\Contract;
use App\Entity\RendezVous;
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
        $compteur->setJoursAlloues((float)self::FULL_YEAR_DAYS);
        $compteur->setJoursConsommes(0.0);

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

        $currentConsumed = $compteur->getJoursConsommes();
        $newConsumed = $currentConsumed + $days;

        $compteur->setJoursConsommes($newConsumed);
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

        $currentConsumed = $compteur->getJoursConsommes();
        $newConsumed = max(0.0, $currentConsumed - $days); // Ne peut pas être négatif

        $compteur->setJoursConsommes($newConsumed);
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
                $compteur->setJoursAlloues((float)self::FULL_YEAR_DAYS);
                $compteur->setJoursConsommes(0.0);

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

    /**
     * Récupère ou crée un compteur pour un utilisateur et une année
     */
    public function getOrCreateCounter(User $user, ?int $year = null): CompteurJoursAnnuels
    {
        $year = $year ?? (int) date('Y');

        $compteur = $this->compteurRepository->findByUserAndYear($user, $year);

        if (!$compteur) {
            $compteur = new CompteurJoursAnnuels();
            $compteur->setUser($user);
            $compteur->setYear($year);
            $compteur->setJoursAlloues((float) self::FULL_YEAR_DAYS);
            $compteur->setJoursConsommes(0.0);

            // Récupérer la date d'embauche depuis le contrat actif
            $activeContract = $this->contractRepository->findOneBy([
                'user' => $user,
                'status' => Contract::STATUS_ACTIVE,
            ]);
            if ($activeContract) {
                $compteur->setDateEmbauche($activeContract->getStartDate());
            }

            $this->entityManager->persist($compteur);
            $this->entityManager->flush();

            $this->logger->info('Compteur jours annuels créé', [
                'user_id' => $user->getId(),
                'year' => $year,
                'jours_alloues' => self::FULL_YEAR_DAYS,
            ]);
        }

        return $compteur;
    }

    /**
     * Ajuste le solde manuellement (par l'admin)
     */
    public function adjustBalance(User $user, float $adjustment, string $comment, User $admin, ?int $year = null): void
    {
        $year = $year ?? (int) date('Y');
        $compteur = $this->compteurRepository->findByUserAndYear($user, $year);

        if (!$compteur) {
            throw new \RuntimeException("Aucun compteur trouvé pour l'utilisateur {$user->getId()} et l'année {$year}");
        }

        $oldAjustement = $compteur->getAjustementAdmin();
        $newAjustement = $oldAjustement + $adjustment;

        $compteur->setAjustementAdmin($newAjustement);
        $compteur->setAjustementComment($comment);
        $this->entityManager->flush();

        $this->logger->info('Ajustement compteur jours annuels admin', [
            'user_id' => $user->getId(),
            'admin_id' => $admin->getId(),
            'year' => $year,
            'adjustment' => $adjustment,
            'comment' => $comment,
            'nouveau_solde_restant' => $compteur->getJoursRestants(),
        ]);
    }

    /**
     * Calcule dynamiquement les jours consommés à partir des affectations validées
     * et des événements hors garde (rendez-vous, réunions, formations)
     */
    public function calculateConsumedDays(User $user, ?int $year = null): float
    {
        $year = $year ?? (int) date('Y');

        $startDate = new \DateTime("{$year}-01-01");
        $endDate = new \DateTime("{$year}-12-31");

        // 1. Jours travaillés depuis les affectations (gardes, renforts)
        $affectations = $this->getValidatedAffectations($user, $startDate, $endDate);

        $totalDays = 0.0;
        foreach ($affectations as $aff) {
            $totalDays += (float) $aff['joursTravailes'];
        }

        // 2. Récupérer les dates de garde pour éviter le double comptage
        $guardDates = $this->getGuardDates($user, $startDate, $endDate);

        // 3. Ajouter les jours d'événements hors garde
        $eventDays = $this->calculateEventDays($user, $startDate, $endDate, $guardDates);
        $totalDays += $eventDays;

        return $totalDays;
    }

    /**
     * Récupère les dates de garde pour un utilisateur (pour éviter le double comptage)
     *
     * @return array<string> Liste des dates au format Y-m-d
     */
    private function getGuardDates(User $user, \DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        $validStatuses = [
            Affectation::STATUS_VALIDATED,
            Affectation::STATUS_TO_REPLACE_ABSENCE,
            Affectation::STATUS_TO_REPLACE_RDV,
            Affectation::STATUS_TO_REPLACE_SCHEDULE_CONFLICT,
        ];

        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('a.startAt, a.endAt')
            ->from(Affectation::class, 'a')
            ->where('a.user = :user')
            ->andWhere('a.startAt >= :start')
            ->andWhere('a.endAt <= :end')
            ->andWhere('a.statut IN (:statuses)')
            ->setParameter('user', $user)
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->setParameter('statuses', $validStatuses);

        $affectations = $qb->getQuery()->getResult();
        $guardDates = [];

        foreach ($affectations as $aff) {
            $current = clone $aff['startAt'];
            $end = $aff['endAt'];

            while ($current <= $end) {
                $guardDates[] = $current->format('Y-m-d');
                $current->modify('+1 day');
            }
        }

        return array_unique($guardDates);
    }

    /**
     * Calcule les jours d'événements (rendez-vous, réunions, formations) hors jours de garde
     */
    private function calculateEventDays(User $user, \DateTimeInterface $startDate, \DateTimeInterface $endDate, array $guardDates): float
    {
        // Récupérer les RDV confirmés ou terminés
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('r.startAt')
            ->from(RendezVous::class, 'r')
            ->leftJoin('r.appointmentParticipants', 'ap')
            ->where('ap.user = :user OR r.organizer = :user')
            ->andWhere('r.startAt >= :start')
            ->andWhere('r.startAt <= :end')
            ->andWhere('r.statut IN (:statuses)')
            ->setParameter('user', $user)
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->setParameter('statuses', [
                RendezVous::STATUS_CONFIRME,
                RendezVous::STATUS_TERMINE,
            ]);

        $rendezVous = $qb->getQuery()->getResult();

        // Compter les dates distinctes qui ne sont pas des jours de garde
        $eventDates = [];
        foreach ($rendezVous as $rdv) {
            $dateStr = $rdv['startAt']->format('Y-m-d');
            if (!in_array($dateStr, $guardDates, true)) {
                $eventDates[$dateStr] = true;
            }
        }

        return (float) count($eventDates);
    }

    /**
     * Retourne la liste des compteurs avec les calculs dynamiques pour l'admin
     *
     * @return array<array{counter: CompteurJoursAnnuels, jours_consommes: float, jours_restants: float, percentage_used: float}>
     */
    public function getCountersWithDynamicData(array $counters): array
    {
        $result = [];

        foreach ($counters as $counter) {
            $user = $counter->getUser();
            if (!$user) {
                continue;
            }

            $year = $counter->getYear();
            $joursConsommes = $this->calculateConsumedDays($user, $year);
            $joursAlloues = $counter->getJoursAlloues();
            $ajustement = $counter->getAjustementAdmin();
            $joursRestants = $joursAlloues - $joursConsommes + $ajustement;
            $percentageUsed = $joursAlloues > 0
                ? round(($joursConsommes / $joursAlloues) * 100, 2)
                : 0;

            $result[] = [
                'counter' => $counter,
                'jours_consommes' => $joursConsommes,
                'jours_restants' => $joursRestants,
                'percentage_used' => $percentageUsed,
            ];
        }

        return $result;
    }

    /**
     * Retourne les détails du compteur pour affichage
     * Calcule dynamiquement les jours consommés à partir des affectations
     */
    public function getBalanceDetails(User $user, ?int $year = null): array
    {
        $year = $year ?? (int) date('Y');
        $compteur = $this->compteurRepository->findByUserAndYear($user, $year);

        if (!$compteur) {
            return [
                'year' => $year,
                'jours_alloues' => 0,
                'jours_consommes' => 0,
                'ajustement' => 0,
                'jours_restants' => 0,
                'percentage_used' => 0,
            ];
        }

        // Calcul dynamique des jours consommés à partir des affectations
        $joursConsommes = $this->calculateConsumedDays($user, $year);
        $joursAlloues = $compteur->getJoursAlloues();
        $ajustement = $compteur->getAjustementAdmin();
        $joursRestants = $joursAlloues - $joursConsommes + $ajustement;

        $percentageUsed = $joursAlloues > 0
            ? round(($joursConsommes / $joursAlloues) * 100, 2)
            : 0;

        return [
            'year' => $compteur->getYear(),
            'jours_alloues' => $joursAlloues,
            'jours_consommes' => $joursConsommes,
            'ajustement' => $ajustement,
            'jours_restants' => $joursRestants,
            'percentage_used' => $percentageUsed,
        ];
    }

    /**
     * Récupère l'historique des mouvements pour un utilisateur
     *
     * @return array<array{date: \DateTimeInterface, type: string, description: string, days: float, balanceAfter: float, comment: ?string}>
     */
    public function getMovementsHistory(User $user, ?int $year = null): array
    {
        $year = $year ?? (int) date('Y');
        $compteur = $this->compteurRepository->findByUserAndYear($user, $year);

        if (!$compteur) {
            return [];
        }

        $joursAlloues = $compteur->getJoursAlloues();
        $ajustement = $compteur->getAjustementAdmin();

        $startDate = new \DateTime("{$year}-01-01");
        $endDate = new \DateTime("{$year}-12-31");

        // Récupérer toutes les consommations (affectations + événements)
        $affectations = $this->getValidatedAffectations($user, $startDate, $endDate);
        $guardDates = $this->getGuardDates($user, $startDate, $endDate);
        $events = $this->getEventsOutsideGuard($user, $startDate, $endDate, $guardDates);

        // Combiner et trier par date ASC pour calculer le solde progressif
        $allConsumptions = [];

        foreach ($affectations as $aff) {
            $days = (float) $aff['joursTravailes'];
            if ($days <= 0) {
                continue;
            }
            $allConsumptions[] = [
                'date' => $aff['startAt'],
                'type' => 'consumption',
                'description' => \sprintf('%s - %s', $aff['type_label'], $aff['villa'] ?? 'Centre'),
                'days' => -$days,
            ];
        }

        foreach ($events as $event) {
            $allConsumptions[] = [
                'date' => $event['date'],
                'type' => 'event',
                'description' => $event['description'],
                'days' => -1, // 1 jour par événement hors garde
            ];
        }

        // Trier par date ASC
        usort($allConsumptions, fn($a, $b) => $a['date'] <=> $b['date']);

        // Construire l'historique avec solde progressif
        $movements = [];
        $runningBalance = $joursAlloues;

        // Allocation initiale au 1er janvier
        $movements[] = [
            'date' => new \DateTime("{$year}-01-01"),
            'type' => 'allocation',
            'description' => "Dotation annuelle {$year}",
            'days' => $joursAlloues,
            'balanceAfter' => $joursAlloues,
            'comment' => null,
        ];

        foreach ($allConsumptions as $consumption) {
            $runningBalance += $consumption['days']; // days est négatif

            $movements[] = [
                'date' => $consumption['date'],
                'type' => $consumption['type'],
                'description' => $consumption['description'],
                'days' => $consumption['days'],
                'balanceAfter' => $runningBalance,
                'comment' => null,
            ];
        }

        // Ajouter l'ajustement admin s'il y en a un
        if ($ajustement != 0) {
            $finalBalance = $runningBalance + $ajustement;

            $movements[] = [
                'date' => $compteur->getUpdatedAt() ?? new \DateTime(),
                'type' => 'adjustment',
                'description' => 'Ajustement administratif',
                'days' => $ajustement,
                'balanceAfter' => $finalBalance,
                'comment' => $compteur->getAjustementComment(),
            ];
        }

        // Trier par date descendante (plus récent en premier)
        usort($movements, fn($a, $b) => $b['date'] <=> $a['date']);

        return $movements;
    }

    /**
     * Récupère les événements (rendez-vous) hors jours de garde avec leurs détails
     */
    private function getEventsOutsideGuard(User $user, \DateTimeInterface $startDate, \DateTimeInterface $endDate, array $guardDates): array
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('r.startAt, r.titre, r.type')
            ->from(RendezVous::class, 'r')
            ->leftJoin('r.appointmentParticipants', 'ap')
            ->where('ap.user = :user OR r.organizer = :user')
            ->andWhere('r.startAt >= :start')
            ->andWhere('r.startAt <= :end')
            ->andWhere('r.statut IN (:statuses)')
            ->setParameter('user', $user)
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->setParameter('statuses', [
                RendezVous::STATUS_CONFIRME,
                RendezVous::STATUS_TERMINE,
            ])
            ->orderBy('r.startAt', 'ASC');

        $rendezVous = $qb->getQuery()->getResult();

        // Grouper par date et ne garder que ceux hors garde
        $eventsByDate = [];
        foreach ($rendezVous as $rdv) {
            $dateStr = $rdv['startAt']->format('Y-m-d');
            if (!in_array($dateStr, $guardDates, true) && !isset($eventsByDate[$dateStr])) {
                $eventsByDate[$dateStr] = [
                    'date' => $rdv['startAt'],
                    'description' => \sprintf('Événement - %s', $rdv['titre'] ?? 'RDV'),
                ];
            }
        }

        return array_values($eventsByDate);
    }

    /**
     * Récupère les affectations validées pour un utilisateur et une période
     */
    private function getValidatedAffectations(User $user, \DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        $validStatuses = [
            Affectation::STATUS_VALIDATED,
            Affectation::STATUS_TO_REPLACE_ABSENCE,
            Affectation::STATUS_TO_REPLACE_RDV,
            Affectation::STATUS_TO_REPLACE_SCHEDULE_CONFLICT,
        ];

        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('a.startAt, a.joursTravailes, a.type, v.nom as villa')
            ->from(Affectation::class, 'a')
            ->leftJoin('a.villa', 'v')
            ->where('a.user = :user')
            ->andWhere('a.startAt >= :start')
            ->andWhere('a.startAt <= :end')
            ->andWhere('a.statut IN (:statuses)')
            ->setParameter('user', $user)
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->setParameter('statuses', $validStatuses)
            ->orderBy('a.startAt', 'ASC');

        $results = $qb->getQuery()->getResult();

        return array_map(function ($aff) {
            return [
                'startAt' => $aff['startAt'],
                'joursTravailes' => (float) ($aff['joursTravailes'] ?? 0),
                'type' => $aff['type'],
                'type_label' => $this->getAffectationTypeLabel($aff['type']),
                'villa' => $aff['villa'],
            ];
        }, $results);
    }

    /**
     * Retourne le libellé du type d'affectation
     */
    private function getAffectationTypeLabel(string $type): string
    {
        return match ($type) {
            'garde_48h' => 'Garde 48h',
            'garde_24h' => 'Garde 24h',
            'renfort' => 'Renfort',
            'autre' => 'Autre',
            default => $type,
        };
    }
}
