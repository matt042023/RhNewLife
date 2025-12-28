<?php

namespace App\Repository;

use App\Entity\Astreinte;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Astreinte>
 */
class AstreinteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Astreinte::class);
    }

    public function save(Astreinte $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Astreinte $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find astreintes for a specific month/year
     *
     * @return Astreinte[]
     */
    public function findByMonth(int $year, int $month): array
    {
        $startOfMonth = new \DateTime("$year-$month-01 00:00:00");
        $endOfMonth = (clone $startOfMonth)->modify('last day of this month')->setTime(23, 59, 59);

        return $this->createQueryBuilder('a')
            ->leftJoin('a.educateur', 'e')
            ->addSelect('e')
            ->where('a.startAt >= :startOfMonth')
            ->andWhere('a.startAt <= :endOfMonth')
            ->setParameter('startOfMonth', $startOfMonth)
            ->setParameter('endOfMonth', $endOfMonth)
            ->orderBy('a.startAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find astreinte for a specific date
     */
    public function findByDate(\DateTimeInterface $date): ?Astreinte
    {
        return $this->createQueryBuilder('a')
            ->where('a.startAt <= :date')
            ->andWhere('a.endAt >= :date')
            ->setParameter('date', $date)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find overlapping astreintes (for validation)
     *
     * @return Astreinte[]
     */
    public function findOverlapping(\DateTimeInterface $startAt, \DateTimeInterface $endAt, ?int $excludeId = null): array
    {
        $qb = $this->createQueryBuilder('a')
            ->where('a.startAt < :endAt')
            ->andWhere('a.endAt > :startAt')
            ->setParameter('startAt', $startAt)
            ->setParameter('endAt', $endAt);

        if ($excludeId) {
            $qb->andWhere('a.id != :excludeId')
               ->setParameter('excludeId', $excludeId);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Get stats for a specific month
     *
     * @return array{total: int, assigned: int, unassigned: int, alerts: int, totalReplacements: int}
     */
    public function getMonthStats(int $year, int $month): array
    {
        $astreintes = $this->findByMonth($year, $month);

        $stats = [
            'total' => count($astreintes),
            'assigned' => 0,
            'unassigned' => 0,
            'alerts' => 0,
            'totalReplacements' => 0,
        ];

        foreach ($astreintes as $astreinte) {
            if ($astreinte->getStatus() === Astreinte::STATUS_ASSIGNED) {
                $stats['assigned']++;
            } elseif ($astreinte->getStatus() === Astreinte::STATUS_UNASSIGNED) {
                $stats['unassigned']++;
            } elseif ($astreinte->getStatus() === Astreinte::STATUS_ALERT) {
                $stats['alerts']++;
            }
            $stats['totalReplacements'] += $astreinte->getReplacementCount();
        }

        return $stats;
    }

    /**
     * Count astreintes for a specific educator in a year
     */
    public function countByEducateurAndYear(User $educateur, int $year): int
    {
        $startOfYear = new \DateTime("$year-01-01 00:00:00");
        $endOfYear = new \DateTime("$year-12-31 23:59:59");

        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.educateur = :educateur')
            ->andWhere('a.startAt >= :startOfYear')
            ->andWhere('a.startAt <= :endOfYear')
            ->setParameter('educateur', $educateur)
            ->setParameter('startOfYear', $startOfYear)
            ->setParameter('endOfYear', $endOfYear)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
