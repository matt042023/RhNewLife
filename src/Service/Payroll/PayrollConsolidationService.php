<?php

namespace App\Service\Payroll;

use App\Entity\Absence;
use App\Entity\Affectation;
use App\Entity\CompteurCP;
use App\Entity\ConsolidationPaie;
use App\Entity\RendezVous;
use App\Entity\User;
use App\Repository\AbsenceRepository;
use App\Repository\AffectationRepository;
use App\Repository\ConsolidationPaieRepository;
use App\Repository\RendezVousRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Service de consolidation mensuelle des données de paie
 */
class PayrollConsolidationService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ConsolidationPaieRepository $consolidationRepository,
        private AffectationRepository $affectationRepository,
        private AbsenceRepository $absenceRepository,
        private RendezVousRepository $rendezVousRepository,
        private UserRepository $userRepository,
        private CPCounterService $cpCounterService,
        private PayrollHistoryService $historyService,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Consolide les données de paie pour un mois donné
     *
     * @return ConsolidationPaie[]
     */
    public function consolidateMonth(int $year, int $month, User $admin): array
    {
        $period = sprintf('%04d-%02d', $year, $month);
        $this->logger->info('Début consolidation mensuelle', ['period' => $period]);

        // Récupérer tous les éducateurs actifs
        $users = $this->userRepository->findActiveEducators();
        $consolidations = [];

        foreach ($users as $user) {
            $consolidation = $this->consolidateForUser($user, $year, $month, $admin);
            if ($consolidation) {
                $consolidations[] = $consolidation;
            }
        }

        $this->logger->info('Consolidation mensuelle terminée', [
            'period' => $period,
            'count' => count($consolidations),
        ]);

        return $consolidations;
    }

    /**
     * Consolide les données de paie pour un utilisateur et un mois donnés
     */
    public function consolidateForUser(User $user, int $year, int $month, User $admin): ?ConsolidationPaie
    {
        $period = sprintf('%04d-%02d', $year, $month);

        // Vérifier si une consolidation existe déjà
        $consolidation = $this->consolidationRepository->findByUserAndPeriod($user, $period);
        $isNew = false;

        if (!$consolidation) {
            $consolidation = new ConsolidationPaie();
            $consolidation->setUser($user);
            $consolidation->setPeriod($period);
            $isNew = true;
        }

        // Calculer les bornes du mois
        $startDate = new \DateTime("{$year}-{$month}-01");
        $endDate = (clone $startDate)->modify('last day of this month');

        // 1. Calculer les jours travaillés (affectations)
        $joursTravailes = $this->calculateWorkingDaysFromAffectations($user, $startDate, $endDate);
        $consolidation->setJoursTravailes(number_format($joursTravailes, 2, '.', ''));

        // 2. Calculer les jours événements (RDV, réunions, formations hors jours de garde)
        $guardDates = $this->getGuardDates($user, $startDate, $endDate);
        $joursEvenements = $this->calculateEventDays($user, $startDate, $endDate, $guardDates);
        $consolidation->setJoursEvenements(number_format($joursEvenements, 2, '.', ''));

        // 3. Agréger les absences par type
        $absences = $this->aggregateAbsences($user, $startDate, $endDate);
        $consolidation->setJoursAbsence($absences);

        // 4. Gérer les CP
        $cpData = $this->calculateCPData($user, $year, $month);
        $consolidation->setCpSoldeDebut(number_format($cpData['solde_debut'], 2, '.', ''));
        $consolidation->setCpAcquis(number_format($cpData['acquis'], 2, '.', ''));
        $consolidation->setCpPris(number_format($cpData['pris'], 2, '.', ''));
        $consolidation->recalculateCpSoldeFin();

        // 5. Calculer le total des variables (sera mis à jour plus tard si besoin)
        $consolidation->recalculateTotalVariables();

        // Persister
        $this->entityManager->persist($consolidation);
        $this->entityManager->flush();

        // Historique
        if ($isNew) {
            $this->historyService->logCreation($consolidation, $admin);
        }

        $this->logger->info('Consolidation utilisateur terminée', [
            'user_id' => $user->getId(),
            'period' => $period,
            'jours_travailes' => $joursTravailes,
            'jours_evenements' => $joursEvenements,
            'cp_acquis' => $cpData['acquis'],
        ]);

        return $consolidation;
    }

    /**
     * Calcule les jours travaillés depuis les affectations (gardes)
     */
    private function calculateWorkingDaysFromAffectations(User $user, \DateTimeInterface $startDate, \DateTimeInterface $endDate): float
    {
        // Statuts comptabilisés pour les jours travaillés
        $validStatuses = [
            Affectation::STATUS_VALIDATED,
            Affectation::STATUS_TO_REPLACE_ABSENCE,
            Affectation::STATUS_TO_REPLACE_RDV,
            Affectation::STATUS_TO_REPLACE_SCHEDULE_CONFLICT,
        ];

        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('SUM(a.joursTravailes)')
            ->from(Affectation::class, 'a')
            ->where('a.user = :user')
            ->andWhere('a.startAt >= :start')
            ->andWhere('a.endAt <= :end')
            ->andWhere('a.statut IN (:statuses)')
            ->setParameter('user', $user)
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->setParameter('statuses', $validStatuses);

        $result = $qb->getQuery()->getSingleScalarResult();

        return (float) ($result ?? 0);
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
                $guardDates[$current->format('Y-m-d')] = true;
                $current->modify('+1 day');
            }
        }

        return array_keys($guardDates);
    }

    /**
     * Calcule les jours d'événements (RDV, réunions, formations) hors jours de garde
     *
     * @param array<string> $guardDates Dates de garde à exclure
     */
    private function calculateEventDays(User $user, \DateTimeInterface $startDate, \DateTimeInterface $endDate, array $guardDates): float
    {
        // Récupérer les RDV confirmés
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

        // Note: Les formations ne sont pas une entité avec date dans ce système
        // Elles sont plus liées aux documents et certificats
        // Si besoin, on peut ajouter une entité FormationSession plus tard

        return (float) count($eventDates);
    }

    /**
     * Agrège les absences validées par type
     *
     * @return array<string, float> Absences par code de type
     */
    private function aggregateAbsences(User $user, \DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('t.code, SUM(a.workingDaysCount) as days')
            ->from(Absence::class, 'a')
            ->leftJoin('a.absenceType', 't')
            ->where('a.user = :user')
            ->andWhere('a.startAt <= :end')
            ->andWhere('a.endAt >= :start')
            ->andWhere('a.status = :status')
            ->setParameter('user', $user)
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->setParameter('status', Absence::STATUS_APPROVED)
            ->groupBy('t.code');

        $results = $qb->getQuery()->getResult();

        $absences = [];
        foreach ($results as $row) {
            $code = $row['code'] ?? 'OTHER';
            $absences[$code] = (float) ($row['days'] ?? 0);
        }

        return $absences;
    }

    /**
     * Calcule les données CP pour le mois
     */
    private function calculateCPData(User $user, int $year, int $month): array
    {
        $period = sprintf('%04d-%02d', $year, $month);

        // Solde début de mois = solde actuel avant crédit mensuel
        $balanceDetails = $this->cpCounterService->getBalanceDetails($user);
        $soldeDebut = $balanceDetails['solde_actuel'];

        // Crédit mensuel
        $cpAcquis = CompteurCP::calculateMonthlyAcquisition($year, $month, $user->getHiringDate());

        // CP pris ce mois (absences CP approuvées)
        $cpPris = $this->calculateCPTakenInMonth($user, $year, $month);

        return [
            'solde_debut' => $soldeDebut,
            'acquis' => $cpAcquis,
            'pris' => $cpPris,
        ];
    }

    /**
     * Calcule les CP pris dans un mois donné
     */
    private function calculateCPTakenInMonth(User $user, int $year, int $month): float
    {
        $startDate = new \DateTime("{$year}-{$month}-01");
        $endDate = (clone $startDate)->modify('last day of this month');

        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('SUM(a.workingDaysCount)')
            ->from(Absence::class, 'a')
            ->leftJoin('a.absenceType', 't')
            ->where('a.user = :user')
            ->andWhere('a.startAt <= :end')
            ->andWhere('a.endAt >= :start')
            ->andWhere('a.status = :status')
            ->andWhere('t.code = :cpCode')
            ->setParameter('user', $user)
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->setParameter('status', Absence::STATUS_APPROVED)
            ->setParameter('cpCode', 'CP');

        $result = $qb->getQuery()->getSingleScalarResult();

        return (float) ($result ?? 0);
    }

    /**
     * Rafraîchit une consolidation existante
     */
    public function refreshConsolidation(ConsolidationPaie $consolidation, User $admin): void
    {
        $user = $consolidation->getUser();
        $year = $consolidation->getYear();
        $month = $consolidation->getMonth();

        if (!$user || !$year || !$month) {
            return;
        }

        // Sauvegarder les anciennes valeurs pour l'historique
        $oldValues = [
            'joursTravailes' => $consolidation->getJoursTravailes(),
            'joursEvenements' => $consolidation->getJoursEvenements(),
            'joursAbsence' => $consolidation->getJoursAbsence(),
            'cpAcquis' => $consolidation->getCpAcquis(),
            'cpPris' => $consolidation->getCpPris(),
        ];

        // Recalculer
        $startDate = new \DateTime("{$year}-{$month}-01");
        $endDate = (clone $startDate)->modify('last day of this month');

        $joursTravailes = $this->calculateWorkingDaysFromAffectations($user, $startDate, $endDate);
        $guardDates = $this->getGuardDates($user, $startDate, $endDate);
        $joursEvenements = $this->calculateEventDays($user, $startDate, $endDate, $guardDates);
        $absences = $this->aggregateAbsences($user, $startDate, $endDate);
        $cpData = $this->calculateCPData($user, $year, $month);

        // Mettre à jour
        $consolidation->setJoursTravailes(number_format($joursTravailes, 2, '.', ''));
        $consolidation->setJoursEvenements(number_format($joursEvenements, 2, '.', ''));
        $consolidation->setJoursAbsence($absences);
        $consolidation->setCpAcquis(number_format($cpData['acquis'], 2, '.', ''));
        $consolidation->setCpPris(number_format($cpData['pris'], 2, '.', ''));
        $consolidation->recalculateCpSoldeFin();
        $consolidation->recalculateTotalVariables();

        $this->entityManager->flush();

        // Log des changements
        $newValues = [
            'joursTravailes' => $consolidation->getJoursTravailes(),
            'joursEvenements' => $consolidation->getJoursEvenements(),
            'joursAbsence' => $consolidation->getJoursAbsence(),
            'cpAcquis' => $consolidation->getCpAcquis(),
            'cpPris' => $consolidation->getCpPris(),
        ];

        foreach ($oldValues as $field => $oldValue) {
            if ($oldValue !== $newValues[$field]) {
                $this->historyService->logUpdate(
                    $consolidation,
                    $field,
                    $oldValue,
                    $newValues[$field],
                    $admin,
                    'Rafraîchissement automatique'
                );
            }
        }

        $this->logger->info('Consolidation rafraîchie', [
            'consolidation_id' => $consolidation->getId(),
        ]);
    }

    /**
     * Récupère ou crée une consolidation pour un utilisateur et une période
     */
    public function getOrCreateConsolidation(User $user, int $year, int $month, User $admin): ConsolidationPaie
    {
        $period = sprintf('%04d-%02d', $year, $month);
        $consolidation = $this->consolidationRepository->findByUserAndPeriod($user, $period);

        if (!$consolidation) {
            $consolidation = $this->consolidateForUser($user, $year, $month, $admin);
        }

        return $consolidation;
    }

    /**
     * Retourne les détails complets d'une consolidation pour affichage
     */
    public function getConsolidationDetails(ConsolidationPaie $consolidation): array
    {
        $user = $consolidation->getUser();
        $year = $consolidation->getYear();
        $month = $consolidation->getMonth();

        if (!$user || !$year || !$month) {
            return [];
        }

        $startDate = new \DateTime("{$year}-{$month}-01");
        $endDate = (clone $startDate)->modify('last day of this month');

        // Récupérer les affectations détaillées
        $affectations = $this->getAffectationsDetails($user, $startDate, $endDate);

        // Récupérer les événements détaillés
        $events = $this->getEventsDetails($user, $startDate, $endDate);

        // Récupérer les absences détaillées
        $absences = $this->getAbsencesDetails($user, $startDate, $endDate);

        // Récupérer les variables détaillées
        $variables = [];
        foreach ($consolidation->getElementsVariables() as $variable) {
            $variables[] = [
                'id' => $variable->getId(),
                'category' => $variable->getCategory(),
                'category_label' => $variable->getCategoryLabel(),
                'label' => $variable->getLabel(),
                'amount' => (float) $variable->getAmount(),
                'description' => $variable->getDescription(),
                'status' => $variable->getStatus(),
            ];
        }

        return [
            'consolidation' => $consolidation,
            'affectations' => $affectations,
            'events' => $events,
            'absences' => $absences,
            'variables' => $variables,
            'totals' => [
                'jours_travailes' => (float) $consolidation->getJoursTravailes(),
                'jours_evenements' => (float) $consolidation->getJoursEvenements(),
                'total_jours_travailes' => $consolidation->getTotalJoursTravailes(),
                'total_jours_absence' => $consolidation->getTotalJoursAbsence(),
                'total_variables' => (float) $consolidation->getTotalVariables(),
            ],
            'cp' => [
                'solde_debut' => (float) $consolidation->getCpSoldeDebut(),
                'acquis' => (float) $consolidation->getCpAcquis(),
                'pris' => (float) $consolidation->getCpPris(),
                'solde_fin' => (float) $consolidation->getCpSoldeFin(),
            ],
        ];
    }

    /**
     * Récupère les détails des affectations pour affichage
     */
    private function getAffectationsDetails(User $user, \DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        $validStatuses = [
            Affectation::STATUS_VALIDATED,
            Affectation::STATUS_TO_REPLACE_ABSENCE,
            Affectation::STATUS_TO_REPLACE_RDV,
            Affectation::STATUS_TO_REPLACE_SCHEDULE_CONFLICT,
        ];

        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('a')
            ->from(Affectation::class, 'a')
            ->where('a.user = :user')
            ->andWhere('a.startAt >= :start')
            ->andWhere('a.endAt <= :end')
            ->andWhere('a.statut IN (:statuses)')
            ->setParameter('user', $user)
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->setParameter('statuses', $validStatuses)
            ->orderBy('a.startAt', 'ASC');

        $affectations = $qb->getQuery()->getResult();

        return array_map(function (Affectation $aff) {
            return [
                'id' => $aff->getId(),
                'start' => $aff->getStartAt()?->format('d/m/Y'),
                'end' => $aff->getEndAt()?->format('d/m/Y'),
                'villa' => $aff->getVilla()?->getName(),
                'type' => $aff->getType(),
                'jours' => $aff->getJoursTravailes(),
            ];
        }, $affectations);
    }

    /**
     * Récupère les détails des événements pour affichage
     */
    private function getEventsDetails(User $user, \DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('r')
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

        $events = $qb->getQuery()->getResult();

        return array_map(function (RendezVous $rdv) {
            return [
                'id' => $rdv->getId(),
                'date' => $rdv->getStartAt()?->format('d/m/Y'),
                'time' => $rdv->getStartAt()?->format('H:i'),
                'title' => $rdv->getTitre(),
                'type' => $rdv->getTypeLabel(),
                'duration' => $rdv->getDurationMinutes(),
            ];
        }, $events);
    }

    /**
     * Récupère les détails des absences pour affichage
     */
    private function getAbsencesDetails(User $user, \DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('a')
            ->from(Absence::class, 'a')
            ->where('a.user = :user')
            ->andWhere('a.startAt <= :end')
            ->andWhere('a.endAt >= :start')
            ->andWhere('a.status = :status')
            ->setParameter('user', $user)
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->setParameter('status', Absence::STATUS_APPROVED)
            ->orderBy('a.startAt', 'ASC');

        $absences = $qb->getQuery()->getResult();

        return array_map(function (Absence $abs) {
            return [
                'id' => $abs->getId(),
                'start' => $abs->getStartAt()?->format('d/m/Y'),
                'end' => $abs->getEndAt()?->format('d/m/Y'),
                'type' => $abs->getAbsenceType()?->getLabel() ?? $abs->getType(),
                'type_code' => $abs->getAbsenceType()?->getCode(),
                'days' => $abs->getWorkingDaysCount(),
            ];
        }, $absences);
    }
}
