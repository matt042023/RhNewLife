<?php

namespace App\Repository;

use App\Entity\CompteurJoursAnnuels;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CompteurJoursAnnuels>
 */
class CompteurJoursAnnuelsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CompteurJoursAnnuels::class);
    }

    /**
     * Trouve le compteur pour l'année en cours d'un utilisateur
     */
    public function findCurrentCounter(User $user): ?CompteurJoursAnnuels
    {
        $currentYear = (int)date('Y');
        return $this->findByUserAndYear($user, $currentYear);
    }

    /**
     * Trouve le compteur pour une année spécifique d'un utilisateur
     */
    public function findByUserAndYear(User $user, int $year): ?CompteurJoursAnnuels
    {
        return $this->createQueryBuilder('c')
            ->where('c.user = :user')
            ->andWhere('c.year = :year')
            ->setParameter('user', $user)
            ->setParameter('year', $year)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouve tous les compteurs d'un utilisateur (historique)
     */
    public function findAllByUser(User $user): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.user = :user')
            ->setParameter('user', $user)
            ->orderBy('c.year', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les compteurs avec un solde faible (pour alertes)
     *
     * @param int $threshold Seuil en dessous duquel le solde est considéré comme faible
     * @param int|null $year Année (null = année en cours)
     */
    public function findCountersNeedingAlert(int $threshold = 10, ?int $year = null): array
    {
        $year = $year ?? (int)date('Y');

        return $this->createQueryBuilder('c')
            ->where('c.year = :year')
            ->andWhere('(c.joursAlloues - c.joursConsommes) < :threshold')
            ->andWhere('(c.joursAlloues - c.joursConsommes) >= 0')
            ->setParameter('year', $year)
            ->setParameter('threshold', $threshold)
            ->orderBy('c.joursAlloues - c.joursConsommes', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les compteurs en solde négatif (dépassement)
     *
     * @param int|null $year Année (null = année en cours)
     */
    public function findNegativeCounters(?int $year = null): array
    {
        $year = $year ?? (int)date('Y');

        return $this->createQueryBuilder('c')
            ->where('c.year = :year')
            ->andWhere('(c.joursAlloues - c.joursConsommes) < 0')
            ->setParameter('year', $year)
            ->orderBy('c.joursAlloues - c.joursConsommes', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve tous les compteurs pour une année donnée
     */
    public function findByYear(int $year): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.year = :year')
            ->setParameter('year', $year)
            ->orderBy('c.user', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Sauvegarde un compteur
     */
    public function save(CompteurJoursAnnuels $compteur, bool $flush = true): void
    {
        $this->getEntityManager()->persist($compteur);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Supprime un compteur
     */
    public function remove(CompteurJoursAnnuels $compteur, bool $flush = true): void
    {
        $this->getEntityManager()->remove($compteur);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Statistiques globales pour une année
     */
    public function getYearStatistics(int $year): array
    {
        $qb = $this->createQueryBuilder('c')
            ->select([
                'COUNT(c.id) as total_counters',
                'SUM(c.joursAlloues) as total_alloues',
                'SUM(c.joursConsommes) as total_consommes',
                'AVG(c.joursAlloues) as avg_alloues',
                'AVG(c.joursConsommes) as avg_consommes',
                'AVG(c.joursAlloues - c.joursConsommes) as avg_remaining',
            ])
            ->where('c.year = :year')
            ->setParameter('year', $year);

        return $qb->getQuery()->getSingleResult();
    }
}
