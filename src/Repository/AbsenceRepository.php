<?php

namespace App\Repository;

use App\Entity\Absence;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Absence>
 */
class AbsenceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Absence::class);
    }
    public function findAllSorted(array $filters = []): array
    {
        $qb = $this->createQueryBuilder('a')
            ->leftJoin('a.user', 'u')
            ->addSelect('u')
            ->leftJoin('a.absenceType', 't')
            ->addSelect('t');

        // Filters
        if (!empty($filters['absenceType'])) {
            $qb->andWhere('a.absenceType = :type')
                ->setParameter('type', $filters['absenceType']);
        }

        if (!empty($filters['status'])) {
            $qb->andWhere('a.status = :status')
                ->setParameter('status', $filters['status']);
        }

        if (!empty($filters['justificationStatus'])) {
            $qb->andWhere('a.justificationStatus = :justificationStatus')
                ->setParameter('justificationStatus', $filters['justificationStatus']);
        }

        if (!empty($filters['dateFrom'])) {
            $qb->andWhere('a.startAt >= :dateFrom')
                ->setParameter('dateFrom', $filters['dateFrom']);
        }

        if (!empty($filters['dateTo'])) {
            $qb->andWhere('a.endAt <= :dateTo')
                ->setParameter('dateTo', $filters['dateTo']);
        }

        // Sorting: Pending first, then by date desc
        $qb->addSelect('CASE WHEN a.status = :pendingStatus THEN 1 ELSE 2 END AS HIDDEN statusOrder')
            ->setParameter('pendingStatus', Absence::STATUS_PENDING)
            ->orderBy('statusOrder', 'ASC')
            ->addOrderBy('a.startAt', 'DESC');

        return $qb->getQuery()->getResult();
    }
}
