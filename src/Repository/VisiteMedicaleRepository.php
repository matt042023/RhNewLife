<?php

namespace App\Repository;

use App\Entity\User;
use App\Entity\VisiteMedicale;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<VisiteMedicale>
 */
class VisiteMedicaleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, VisiteMedicale::class);
    }

    /**
     * Save entity
     */
    public function save(VisiteMedicale $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Remove entity
     */
    public function remove(VisiteMedicale $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find all visits with filters and sorting
     */
    public function findAllSorted(array $filters = []): array
    {
        $qb = $this->createQueryBuilder('v')
            ->leftJoin('v.user', 'u')
            ->addSelect('u');

        // Filter by user
        if (!empty($filters['user'])) {
            $qb->andWhere('v.user = :user')
                ->setParameter('user', $filters['user']);
        }

        // Filter by type
        if (!empty($filters['type'])) {
            $qb->andWhere('v.type = :type')
                ->setParameter('type', $filters['type']);
        }

        // Filter by status
        if (!empty($filters['status'])) {
            $qb->andWhere('v.status = :status')
                ->setParameter('status', $filters['status']);
        }

        // Filter by aptitude
        if (!empty($filters['aptitude'])) {
            $qb->andWhere('v.aptitude = :aptitude')
                ->setParameter('aptitude', $filters['aptitude']);
        }

        // Filter by date range (visit date)
        if (!empty($filters['dateFrom'])) {
            $qb->andWhere('v.visitDate >= :dateFrom')
                ->setParameter('dateFrom', $filters['dateFrom']);
        }

        if (!empty($filters['dateTo'])) {
            $qb->andWhere('v.visitDate <= :dateTo')
                ->setParameter('dateTo', $filters['dateTo']);
        }

        // Filter expiring soon
        if (!empty($filters['expiringSoon'])) {
            $threshold = new \DateTime('+30 days');
            $qb->andWhere('v.expiryDate <= :threshold')
                ->andWhere('v.expiryDate >= :today')
                ->setParameter('threshold', $threshold)
                ->setParameter('today', new \DateTime('today'));
        }

        // Filter expired
        if (!empty($filters['expired'])) {
            $qb->andWhere('v.expiryDate < :today')
                ->setParameter('today', new \DateTime('today'));
        }

        // Default sorting: most recent first, then by expiry date
        $qb->orderBy('v.visitDate', 'DESC')
            ->addOrderBy('v.expiryDate', 'ASC');

        return $qb->getQuery()->getResult();
    }

    /**
     * Find visits by user
     */
    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('v')
            ->andWhere('v.user = :user')
            ->setParameter('user', $user)
            ->orderBy('v.visitDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find upcoming renewals (expiring within specified days)
     */
    public function findUpcomingRenewals(int $days = 30): array
    {
        $today = new \DateTime('today');
        $threshold = new \DateTime("+{$days} days");

        return $this->createQueryBuilder('v')
            ->leftJoin('v.user', 'u')
            ->addSelect('u')
            ->andWhere('v.expiryDate BETWEEN :today AND :threshold')
            ->setParameter('today', $today)
            ->setParameter('threshold', $threshold)
            ->orderBy('v.expiryDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find expired visits
     */
    public function findExpired(): array
    {
        return $this->createQueryBuilder('v')
            ->leftJoin('v.user', 'u')
            ->addSelect('u')
            ->andWhere('v.expiryDate < :today')
            ->setParameter('today', new \DateTime('today'))
            ->orderBy('v.expiryDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find visits by date range
     */
    public function findByDateRange(\DateTimeInterface $from, \DateTimeInterface $to): array
    {
        return $this->createQueryBuilder('v')
            ->leftJoin('v.user', 'u')
            ->addSelect('u')
            ->andWhere('v.visitDate BETWEEN :from AND :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('v.visitDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find latest visit by user and type
     */
    public function findLatestByUserAndType(User $user, string $type): ?VisiteMedicale
    {
        return $this->createQueryBuilder('v')
            ->andWhere('v.user = :user')
            ->andWhere('v.type = :type')
            ->setParameter('user', $user)
            ->setParameter('type', $type)
            ->orderBy('v.visitDate', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find programmees (scheduled) visits
     */
    public function findProgrammees(): array
    {
        return $this->createQueryBuilder('v')
            ->leftJoin('v.user', 'u')
            ->addSelect('u')
            ->andWhere('v.status = :status')
            ->setParameter('status', VisiteMedicale::STATUS_PROGRAMMEE)
            ->orderBy('v.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find visit by appointment
     */
    public function findByAppointment($appointment): ?VisiteMedicale
    {
        return $this->createQueryBuilder('v')
            ->andWhere('v.appointment = :appointment')
            ->setParameter('appointment', $appointment)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Get statistics for dashboard
     */
    public function getStatistics(): array
    {
        $total = $this->count([]);
        $expiringSoon = count($this->findUpcomingRenewals(30));
        $expired = count($this->findExpired());

        // Count by status
        $byStatus = $this->createQueryBuilder('v')
            ->select('v.status, COUNT(v.id) as count')
            ->groupBy('v.status')
            ->getQuery()
            ->getResult();

        // Count by type
        $byType = $this->createQueryBuilder('v')
            ->select('v.type, COUNT(v.id) as count')
            ->groupBy('v.type')
            ->getQuery()
            ->getResult();

        // Count by aptitude
        $byAptitude = $this->createQueryBuilder('v')
            ->select('v.aptitude, COUNT(v.id) as count')
            ->groupBy('v.aptitude')
            ->getQuery()
            ->getResult();

        return [
            'total' => $total,
            'expiringSoon' => $expiringSoon,
            'expired' => $expired,
            'byStatus' => $byStatus,
            'byType' => $byType,
            'byAptitude' => $byAptitude,
        ];
    }
}
