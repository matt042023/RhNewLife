<?php

namespace App\Repository;

use App\Entity\CompteurAbsence;
use App\Entity\TypeAbsence;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CompteurAbsence>
 */
class CompteurAbsenceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CompteurAbsence::class);
    }

    /**
     * Find counter for a specific user, absence type and year
     */
    public function findByUserTypeAndYear(User $user, TypeAbsence $absenceType, int $year): ?CompteurAbsence
    {
        return $this->createQueryBuilder('c')
            ->where('c.user = :user')
            ->andWhere('c.absenceType = :type')
            ->andWhere('c.year = :year')
            ->setParameter('user', $user)
            ->setParameter('type', $absenceType)
            ->setParameter('year', $year)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find all counters for a user in a specific year
     *
     * @return CompteurAbsence[]
     */
    public function findByUserAndYear(User $user, int $year): array
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.absenceType', 't')
            ->where('c.user = :user')
            ->andWhere('c.year = :year')
            ->setParameter('user', $user)
            ->setParameter('year', $year)
            ->orderBy('t.label', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find all negative counters (for alerts)
     *
     * @return CompteurAbsence[]
     */
    public function findNegativeCounters(): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.taken > c.earned')
            ->orderBy('c.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find counters by user
     *
     * @return CompteurAbsence[]
     */
    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.absenceType', 't')
            ->where('c.user = :user')
            ->setParameter('user', $user)
            ->orderBy('c.year', 'DESC')
            ->addOrderBy('t.label', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
