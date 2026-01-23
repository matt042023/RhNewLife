<?php

namespace App\Repository;

use App\Entity\ConsolidationPaie;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ConsolidationPaie>
 */
class ConsolidationPaieRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ConsolidationPaie::class);
    }

    /**
     * Trouve une consolidation par utilisateur et période
     */
    public function findByUserAndPeriod(User $user, string $period): ?ConsolidationPaie
    {
        return $this->findOneBy([
            'user' => $user,
            'period' => $period,
        ]);
    }

    /**
     * Trouve toutes les consolidations d'un mois donné
     *
     * @return ConsolidationPaie[]
     */
    public function findByPeriod(string $period): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.period = :period')
            ->setParameter('period', $period)
            ->join('c.user', 'u')
            ->orderBy('u.lastName', 'ASC')
            ->addOrderBy('u.firstName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve toutes les consolidations d'un mois avec un statut donné
     *
     * @return ConsolidationPaie[]
     */
    public function findByPeriodAndStatus(string $period, string $status): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.period = :period')
            ->andWhere('c.status = :status')
            ->setParameter('period', $period)
            ->setParameter('status', $status)
            ->join('c.user', 'u')
            ->orderBy('u.lastName', 'ASC')
            ->addOrderBy('u.firstName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve toutes les consolidations d'un utilisateur
     *
     * @return ConsolidationPaie[]
     */
    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.user = :user')
            ->setParameter('user', $user)
            ->orderBy('c.period', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les consolidations validées d'un utilisateur (visibles par l'éducateur)
     *
     * @return ConsolidationPaie[]
     */
    public function findValidatedByUser(User $user): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.user = :user')
            ->andWhere('c.status IN (:statuses)')
            ->setParameter('user', $user)
            ->setParameter('statuses', [
                ConsolidationPaie::STATUS_VALIDATED,
                ConsolidationPaie::STATUS_EXPORTED,
                ConsolidationPaie::STATUS_ARCHIVED,
            ])
            ->orderBy('c.period', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte les consolidations par statut pour un mois donné
     *
     * @return array<string, int>
     */
    public function countByStatusForPeriod(string $period): array
    {
        $result = $this->createQueryBuilder('c')
            ->select('c.status, COUNT(c.id) as count')
            ->andWhere('c.period = :period')
            ->setParameter('period', $period)
            ->groupBy('c.status')
            ->getQuery()
            ->getResult();

        $counts = [];
        foreach ($result as $row) {
            $counts[$row['status']] = (int) $row['count'];
        }

        return $counts;
    }

    /**
     * Trouve les périodes disponibles (liste des mois avec au moins une consolidation)
     *
     * @return string[]
     */
    public function findAvailablePeriods(): array
    {
        $result = $this->createQueryBuilder('c')
            ->select('DISTINCT c.period')
            ->orderBy('c.period', 'DESC')
            ->getQuery()
            ->getResult();

        return array_column($result, 'period');
    }

    /**
     * Trouve les consolidations non validées pour un mois (pour rappel)
     *
     * @return ConsolidationPaie[]
     */
    public function findPendingValidation(string $period): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.period = :period')
            ->andWhere('c.status = :status')
            ->setParameter('period', $period)
            ->setParameter('status', ConsolidationPaie::STATUS_DRAFT)
            ->join('c.user', 'u')
            ->orderBy('u.lastName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Vérifie si toutes les consolidations d'un mois sont validées
     */
    public function areAllValidated(string $period): bool
    {
        $count = $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.period = :period')
            ->andWhere('c.status = :status')
            ->setParameter('period', $period)
            ->setParameter('status', ConsolidationPaie::STATUS_DRAFT)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $count === 0;
    }

    /**
     * Trouve toutes les consolidations d'un utilisateur pour une année
     *
     * @return ConsolidationPaie[]
     */
    public function findByUserAndYear(User $user, int $year): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.user = :user')
            ->andWhere('c.period LIKE :yearPattern')
            ->setParameter('user', $user)
            ->setParameter('yearPattern', $year . '-%')
            ->orderBy('c.period', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
