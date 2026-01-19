<?php

namespace App\Service\Payroll;

use App\Entity\Absence;
use App\Entity\CompteurCP;
use App\Entity\User;
use App\Repository\AbsenceRepository;
use App\Repository\CompteurCPRepository;
use App\Repository\ConsolidationPaieRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Service de gestion des compteurs de congés payés
 */
class CPCounterService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CompteurCPRepository $compteurCPRepository,
        private ConsolidationPaieRepository $consolidationPaieRepository,
        private AbsenceRepository $absenceRepository,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Récupère ou crée le compteur CP d'un utilisateur pour une période donnée
     */
    public function getOrCreateCounter(User $user, ?string $periodeReference = null): CompteurCP
    {
        $periodeReference ??= CompteurCP::getCurrentPeriodeReference();

        $compteur = $this->compteurCPRepository->findByUserAndPeriode($user, $periodeReference);

        if (!$compteur) {
            $compteur = $this->createCounter($user, $periodeReference);
        }

        return $compteur;
    }

    /**
     * Crée un nouveau compteur CP
     */
    public function createCounter(User $user, string $periodeReference): CompteurCP
    {
        $compteur = new CompteurCP();
        $compteur->setUser($user);
        $compteur->setPeriodeReference($periodeReference);

        // Calcul du solde initial basé sur le prorata d'embauche
        $initialBalance = $this->calculateInitialBalance($user, $periodeReference);
        $compteur->setSoldeInitial(number_format($initialBalance, 2, '.', ''));

        $this->entityManager->persist($compteur);
        $this->entityManager->flush();

        $this->logger->info('Compteur CP créé', [
            'user_id' => $user->getId(),
            'periode' => $periodeReference,
            'solde_initial' => $initialBalance,
        ]);

        return $compteur;
    }

    /**
     * Calcule le solde initial pour un nouveau compteur
     * Prend en compte la date d'embauche si elle est dans la période
     */
    private function calculateInitialBalance(User $user, string $periodeReference): float
    {
        // Chercher le compteur de la période précédente pour le report
        $previousPeriode = $this->getPreviousPeriode($periodeReference);
        $previousCompteur = $this->compteurCPRepository->findByUserAndPeriode($user, $previousPeriode);

        if ($previousCompteur) {
            // Report du solde de la période précédente
            return $previousCompteur->getSoldeActuel();
        }

        // Pas de période précédente : calculer le prorata depuis l'embauche
        $hiringDate = $user->getHiringDate();
        if (!$hiringDate) {
            return 0;
        }

        // Calculer les CP acquis depuis l'embauche jusqu'au début de cette période
        $periodeStart = $this->getPeriodeStartDate($periodeReference);

        if ($hiringDate >= $periodeStart) {
            // Embauché dans cette période ou après, pas de report
            return 0;
        }

        // L'utilisateur était déjà là avant cette période
        // On ne peut pas calculer automatiquement, l'admin devra ajuster
        return 0;
    }

    /**
     * Crédite les CP mensuels pour un utilisateur
     */
    public function creditMonthlyCP(User $user, int $year, int $month): float
    {
        $compteur = $this->getOrCreateCounter($user);
        $hiringDate = $user->getHiringDate();

        // Calculer les CP acquis pour ce mois (avec prorata si embauche en cours de mois)
        $cpAcquis = CompteurCP::calculateMonthlyAcquisition($year, $month, $hiringDate);

        if ($cpAcquis > 0) {
            $compteur->addAcquis($cpAcquis);
            $this->entityManager->flush();

            $this->logger->info('CP mensuels crédités', [
                'user_id' => $user->getId(),
                'year' => $year,
                'month' => $month,
                'cp_acquis' => $cpAcquis,
                'nouveau_total_acquis' => $compteur->getAcquis(),
            ]);
        }

        return $cpAcquis;
    }

    /**
     * Déduit des CP du compteur
     */
    public function deductCP(User $user, float $days): void
    {
        if ($days <= 0) {
            return;
        }

        $compteur = $this->getOrCreateCounter($user);
        $compteur->addPris($days);
        $this->entityManager->flush();

        $this->logger->info('CP déduits', [
            'user_id' => $user->getId(),
            'jours_deduits' => $days,
            'nouveau_solde' => $compteur->getSoldeActuel(),
        ]);
    }

    /**
     * Annule une déduction de CP (si absence annulée)
     */
    public function cancelDeduction(User $user, float $days): void
    {
        if ($days <= 0) {
            return;
        }

        $compteur = $this->getOrCreateCounter($user);
        $currentPris = (float) $compteur->getPris();
        $newPris = max(0, $currentPris - $days);
        $compteur->setPris(number_format($newPris, 2, '.', ''));
        $this->entityManager->flush();

        $this->logger->info('Déduction CP annulée', [
            'user_id' => $user->getId(),
            'jours_restitues' => $days,
            'nouveau_solde' => $compteur->getSoldeActuel(),
        ]);
    }

    /**
     * Ajuste le solde CP manuellement (par l'admin)
     */
    public function adjustBalance(User $user, float $adjustment, string $comment, User $admin): void
    {
        $compteur = $this->getOrCreateCounter($user);

        $oldAjustement = (float) $compteur->getAjustementAdmin();
        $newAjustement = $oldAjustement + $adjustment;

        $compteur->setAjustementAdmin(number_format($newAjustement, 2, '.', ''));
        $compteur->setAjustementComment($comment);
        $this->entityManager->flush();

        $this->logger->info('Ajustement CP admin', [
            'user_id' => $user->getId(),
            'admin_id' => $admin->getId(),
            'adjustment' => $adjustment,
            'comment' => $comment,
            'nouveau_solde' => $compteur->getSoldeActuel(),
        ]);
    }

    /**
     * Retourne le solde CP actuel d'un utilisateur
     */
    public function getCurrentBalance(User $user): float
    {
        $compteur = $this->compteurCPRepository->findCurrentByUser($user);
        return $compteur ? $compteur->getSoldeActuel() : 0;
    }

    /**
     * Retourne les détails du compteur CP
     */
    public function getBalanceDetails(User $user): array
    {
        $compteur = $this->getOrCreateCounter($user);

        return [
            'periode' => $compteur->getPeriodeReference(),
            'periode_label' => $compteur->getPeriodeLabel(),
            'solde_initial' => (float) $compteur->getSoldeInitial(),
            'acquis' => (float) $compteur->getAcquis(),
            'pris' => (float) $compteur->getPris(),
            'ajustement' => (float) $compteur->getAjustementAdmin(),
            'solde_actuel' => $compteur->getSoldeActuel(),
        ];
    }

    /**
     * Vérifie si le solde est suffisant pour prendre X jours
     */
    public function hasSufficientBalance(User $user, float $days): bool
    {
        return $this->getCurrentBalance($user) >= $days;
    }

    /**
     * Retourne la période précédente
     */
    private function getPreviousPeriode(string $periodeReference): string
    {
        $startYear = (int) substr($periodeReference, 0, 4);
        return ($startYear - 1) . '-' . $startYear;
    }

    /**
     * Retourne la date de début d'une période
     */
    private function getPeriodeStartDate(string $periodeReference): \DateTimeInterface
    {
        $startYear = (int) substr($periodeReference, 0, 4);
        return new \DateTime("{$startYear}-06-01");
    }

    /**
     * Crée une nouvelle période (report de l'ancienne)
     * Appelé le 1er juin de chaque année
     */
    public function createNewPeriod(User $user): CompteurCP
    {
        $newPeriode = CompteurCP::getCurrentPeriodeReference();
        $previousPeriode = $this->getPreviousPeriode($newPeriode);

        // Récupérer le compteur précédent
        $previousCompteur = $this->compteurCPRepository->findByUserAndPeriode($user, $previousPeriode);

        // Créer le nouveau compteur
        $newCompteur = new CompteurCP();
        $newCompteur->setUser($user);
        $newCompteur->setPeriodeReference($newPeriode);

        // Reporter le solde
        if ($previousCompteur) {
            $soldeReport = $previousCompteur->getSoldeActuel();
            $newCompteur->setSoldeInitial(number_format($soldeReport, 2, '.', ''));
        }

        $this->entityManager->persist($newCompteur);
        $this->entityManager->flush();

        $this->logger->info('Nouvelle période CP créée avec report', [
            'user_id' => $user->getId(),
            'nouvelle_periode' => $newPeriode,
            'solde_reporte' => $newCompteur->getSoldeInitial(),
        ]);

        return $newCompteur;
    }

    /**
     * Crée un compteur pour une nouvelle période avec un solde de report spécifié
     * Utilisé par la commande CRON de nouvelle période
     */
    public function createNewPeriodCounter(User $user, string $periodeReference, float $soldeReport): CompteurCP
    {
        $compteur = new CompteurCP();
        $compteur->setUser($user);
        $compteur->setPeriodeReference($periodeReference);
        $compteur->setSoldeInitial(number_format($soldeReport, 2, '.', ''));

        $this->entityManager->persist($compteur);
        $this->entityManager->flush();

        $this->logger->info('Compteur CP nouvelle période créé', [
            'user_id' => $user->getId(),
            'periode' => $periodeReference,
            'solde_report' => $soldeReport,
        ]);

        return $compteur;
    }

    /**
     * Calcule le prorata d'acquisition pour un mois donné
     * Retourne 1.0 si mois complet, sinon le ratio basé sur la date d'embauche
     */
    public function calculateProrata(User $user, int $year, int $month): float
    {
        $hiringDate = $user->getHiringDate();

        if (!$hiringDate) {
            return 1.0;
        }

        $monthStart = new \DateTime(sprintf('%04d-%02d-01', $year, $month));
        $monthEnd = (clone $monthStart)->modify('last day of this month');

        // Si embauché après la fin du mois, pas d'acquisition
        if ($hiringDate > $monthEnd) {
            return 0.0;
        }

        // Si embauché avant le début du mois, acquisition complète
        if ($hiringDate < $monthStart) {
            return 1.0;
        }

        // Embauché pendant le mois : prorata au jour calendaire
        $totalDays = (int) $monthEnd->format('j');
        $daysWorked = $totalDays - (int) $hiringDate->format('j') + 1;

        return max(0, min(1, $daysWorked / $totalDays));
    }

    /**
     * Récupère l'historique des mouvements CP pour un utilisateur
     * Basé sur les consolidations de paie et les ajustements
     *
     * @return array<array{date: \DateTimeInterface, type: string, description: string, days: float, balanceAfter: float, comment: ?string}>
     */
    public function getMovementsHistory(User $user): array
    {
        $movements = [];
        $compteur = $this->getOrCreateCounter($user);
        $periodeReference = $compteur->getPeriodeReference();

        // Récupérer les consolidations de la période courante (juin N à mai N+1)
        $years = explode('-', $periodeReference);
        $startYear = (int) $years[0];
        $endYear = (int) $years[1];

        // Mois de juin N à décembre N
        for ($month = 6; $month <= 12; $month++) {
            $period = sprintf('%04d-%02d', $startYear, $month);
            $consolidation = $this->consolidationPaieRepository->findByUserAndPeriod($user, $period);
            if ($consolidation) {
                $cpAcquis = (float) $consolidation->getCpAcquis();
                $cpPris = (float) $consolidation->getCpPris();

                if ($cpAcquis > 0) {
                    $movements[] = [
                        'date' => new \DateTime(sprintf('%04d-%02d-01', $startYear, $month)),
                        'type' => 'credit',
                        'description' => sprintf('Acquisition CP %s %d', $this->getMonthName($month), $startYear),
                        'days' => $cpAcquis,
                        'balanceAfter' => 0, // Sera recalculé
                        'comment' => null,
                    ];
                }

                if ($cpPris > 0) {
                    $movements[] = [
                        'date' => new \DateTime(sprintf('%04d-%02d-15', $startYear, $month)),
                        'type' => 'debit',
                        'description' => sprintf('CP pris %s %d', $this->getMonthName($month), $startYear),
                        'days' => -$cpPris,
                        'balanceAfter' => 0,
                        'comment' => null,
                    ];
                }
            }
        }

        // Mois de janvier N+1 à mai N+1
        for ($month = 1; $month <= 5; $month++) {
            $period = sprintf('%04d-%02d', $endYear, $month);
            $consolidation = $this->consolidationPaieRepository->findByUserAndPeriod($user, $period);
            if ($consolidation) {
                $cpAcquis = (float) $consolidation->getCpAcquis();
                $cpPris = (float) $consolidation->getCpPris();

                if ($cpAcquis > 0) {
                    $movements[] = [
                        'date' => new \DateTime(sprintf('%04d-%02d-01', $endYear, $month)),
                        'type' => 'credit',
                        'description' => sprintf('Acquisition CP %s %d', $this->getMonthName($month), $endYear),
                        'days' => $cpAcquis,
                        'balanceAfter' => 0,
                        'comment' => null,
                    ];
                }

                if ($cpPris > 0) {
                    $movements[] = [
                        'date' => new \DateTime(sprintf('%04d-%02d-15', $endYear, $month)),
                        'type' => 'debit',
                        'description' => sprintf('CP pris %s %d', $this->getMonthName($month), $endYear),
                        'days' => -$cpPris,
                        'balanceAfter' => 0,
                        'comment' => null,
                    ];
                }
            }
        }

        // Ajouter le solde initial en premier s'il est non nul
        $soldeInitial = (float) $compteur->getSoldeInitial();
        if ($soldeInitial != 0) {
            array_unshift($movements, [
                'date' => new \DateTime(sprintf('%04d-06-01', $startYear)),
                'type' => 'report',
                'description' => 'Report période précédente',
                'days' => $soldeInitial,
                'balanceAfter' => $soldeInitial,
                'comment' => null,
            ]);
        }

        // Ajouter l'ajustement admin s'il y en a un
        $ajustement = (float) $compteur->getAjustementAdmin();
        if ($ajustement != 0) {
            $movements[] = [
                'date' => $compteur->getUpdatedAt() ?? new \DateTime(),
                'type' => 'adjustment',
                'description' => 'Ajustement administratif',
                'days' => $ajustement,
                'balanceAfter' => 0,
                'comment' => $compteur->getAjustementComment(),
            ];
        }

        // Trier par date
        usort($movements, fn($a, $b) => $a['date'] <=> $b['date']);

        // Recalculer les soldes après chaque mouvement
        $balance = 0;
        foreach ($movements as &$movement) {
            $balance += $movement['days'];
            $movement['balanceAfter'] = $balance;
        }

        // Inverser pour avoir les plus récents en premier
        return array_reverse($movements);
    }

    /**
     * Récupère les absences CP en attente ou approuvées (futures)
     *
     * @return Absence[]
     */
    public function getPendingCPAbsences(User $user): array
    {
        $today = new \DateTime('today');

        return $this->absenceRepository->createQueryBuilder('a')
            ->join('a.absenceType', 't')
            ->andWhere('a.user = :user')
            ->andWhere('t.deductFromCounter = true')
            ->andWhere('a.status IN (:statuses)')
            ->andWhere('a.startAt >= :today')
            ->setParameter('user', $user)
            ->setParameter('statuses', [Absence::STATUS_PENDING, Absence::STATUS_APPROVED])
            ->setParameter('today', $today)
            ->orderBy('a.startAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Retourne le nom du mois en français
     */
    private function getMonthName(int $month): string
    {
        $months = [
            1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril',
            5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août',
            9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre',
        ];
        return $months[$month] ?? '';
    }
}
