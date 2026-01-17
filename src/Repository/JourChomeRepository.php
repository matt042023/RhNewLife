<?php

namespace App\Repository;

use App\Entity\JourChome;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<JourChome>
 */
class JourChomeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, JourChome::class);
    }

    /**
     * Find all jours chômés for an educator in a specific month
     *
     * @return JourChome[]
     */
    public function findByEducateurAndMonth(User $user, int $year, int $month): array
    {
        $startDate = new \DateTime("$year-$month-01");
        $endDate = (clone $startDate)->modify('last day of this month');

        return $this->createQueryBuilder('jc')
            ->andWhere('jc.educateur = :user')
            ->andWhere('jc.date >= :startDate')
            ->andWhere('jc.date <= :endDate')
            ->setParameter('user', $user)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('jc.date', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find all jours chômés for an educator in the same ISO week as the given date
     *
     * @return JourChome[]
     */
    public function findByEducateurAndWeek(User $user, \DateTimeInterface $date): array
    {
        // Get the Monday and Sunday of the week containing the date
        $weekStart = (clone $date);
        if ($weekStart instanceof \DateTime) {
            $weekStart->modify('monday this week');
        } else {
            $weekStart = \DateTime::createFromInterface($weekStart)->modify('monday this week');
        }

        $weekEnd = (clone $weekStart)->modify('+6 days');

        return $this->createQueryBuilder('jc')
            ->andWhere('jc.educateur = :user')
            ->andWhere('jc.date >= :weekStart')
            ->andWhere('jc.date <= :weekEnd')
            ->setParameter('user', $user)
            ->setParameter('weekStart', $weekStart)
            ->setParameter('weekEnd', $weekEnd)
            ->orderBy('jc.date', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Count jours chômés for an educator in the same ISO week as the given date
     */
    public function countByEducateurAndWeek(User $user, \DateTimeInterface $date): int
    {
        $weekStart = (clone $date);
        if ($weekStart instanceof \DateTime) {
            $weekStart->modify('monday this week');
        } else {
            $weekStart = \DateTime::createFromInterface($weekStart)->modify('monday this week');
        }

        $weekEnd = (clone $weekStart)->modify('+6 days');

        return (int) $this->createQueryBuilder('jc')
            ->select('COUNT(jc.id)')
            ->andWhere('jc.educateur = :user')
            ->andWhere('jc.date >= :weekStart')
            ->andWhere('jc.date <= :weekEnd')
            ->setParameter('user', $user)
            ->setParameter('weekStart', $weekStart)
            ->setParameter('weekEnd', $weekEnd)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Find all jours chômés for a specific month (all educators)
     *
     * @return JourChome[]
     */
    public function findByMonth(int $year, int $month): array
    {
        $startDate = new \DateTime("$year-$month-01");
        $endDate = (clone $startDate)->modify('last day of this month');

        return $this->createQueryBuilder('jc')
            ->leftJoin('jc.educateur', 'e')
            ->addSelect('e')
            ->andWhere('jc.date >= :startDate')
            ->andWhere('jc.date <= :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('jc.date', 'ASC')
            ->addOrderBy('e.lastName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Check if a jour chômé already exists for this educator on this date
     */
    public function existsByEducateurAndDate(User $user, \DateTimeInterface $date): bool
    {
        return (bool) $this->createQueryBuilder('jc')
            ->select('COUNT(jc.id)')
            ->andWhere('jc.educateur = :user')
            ->andWhere('jc.date = :date')
            ->setParameter('user', $user)
            ->setParameter('date', $date)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Find a jour chômé by educator and date
     */
    public function findOneByEducateurAndDate(User $user, \DateTimeInterface $date): ?JourChome
    {
        return $this->createQueryBuilder('jc')
            ->andWhere('jc.educateur = :user')
            ->andWhere('jc.date = :date')
            ->setParameter('user', $user)
            ->setParameter('date', $date)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
