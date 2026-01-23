<?php

namespace App\Repository;

use App\Entity\CompteurCP;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CompteurCP>
 */
class CompteurCPRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CompteurCP::class);
    }

    /**
     * Trouve le compteur CP d'un utilisateur pour une période donnée
     */
    public function findByUserAndPeriode(User $user, string $periodeReference): ?CompteurCP
    {
        return $this->findOneBy([
            'user' => $user,
            'periodeReference' => $periodeReference,
        ]);
    }

    /**
     * Trouve le compteur CP actuel d'un utilisateur
     */
    public function findCurrentByUser(User $user): ?CompteurCP
    {
        $currentPeriode = CompteurCP::getCurrentPeriodeReference();
        return $this->findByUserAndPeriode($user, $currentPeriode);
    }

    /**
     * Trouve tous les compteurs CP d'un utilisateur
     *
     * @return CompteurCP[]
     */
    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.user = :user')
            ->setParameter('user', $user)
            ->orderBy('c.periodeReference', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve tous les compteurs CP pour une période donnée
     *
     * @return CompteurCP[]
     */
    public function findByPeriode(string $periodeReference): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.periodeReference = :periode')
            ->setParameter('periode', $periodeReference)
            ->join('c.user', 'u')
            ->orderBy('u.lastName', 'ASC')
            ->addOrderBy('u.firstName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve tous les compteurs CP de la période actuelle
     *
     * @return CompteurCP[]
     */
    public function findAllCurrent(): array
    {
        $currentPeriode = CompteurCP::getCurrentPeriodeReference();
        return $this->findByPeriode($currentPeriode);
    }

    /**
     * Trouve les utilisateurs sans compteur CP pour la période actuelle
     *
     * @return User[]
     */
    public function findUsersWithoutCurrentCounter(): array
    {
        $currentPeriode = CompteurCP::getCurrentPeriodeReference();

        $em = $this->getEntityManager();

        $subQuery = $em->createQueryBuilder()
            ->select('IDENTITY(c.user)')
            ->from(CompteurCP::class, 'c')
            ->where('c.periodeReference = :periode');

        return $em->createQueryBuilder()
            ->select('u')
            ->from(User::class, 'u')
            ->where('u.status = :status')
            ->andWhere('u.id NOT IN (' . $subQuery->getDQL() . ')')
            ->setParameter('status', User::STATUS_ACTIVE)
            ->setParameter('periode', $currentPeriode)
            ->orderBy('u.lastName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Calcule le solde total des CP pour une période
     */
    public function getTotalBalanceByPeriode(string $periodeReference): float
    {
        $compteurs = $this->findByPeriode($periodeReference);
        $total = 0;

        foreach ($compteurs as $compteur) {
            $total += $compteur->getSoldeActuel();
        }

        return $total;
    }

    /**
     * Trouve les compteurs avec un solde négatif
     *
     * @return CompteurCP[]
     */
    public function findNegativeBalances(string $periodeReference): array
    {
        // On doit calculer le solde en PHP car c'est une méthode calculée
        $compteurs = $this->findByPeriode($periodeReference);

        return array_filter($compteurs, fn(CompteurCP $c) => $c->getSoldeActuel() < 0);
    }

    /**
     * Trouve les périodes disponibles
     *
     * @return string[]
     */
    public function findAvailablePeriodes(): array
    {
        $result = $this->createQueryBuilder('c')
            ->select('DISTINCT c.periodeReference')
            ->orderBy('c.periodeReference', 'DESC')
            ->getQuery()
            ->getResult();

        return array_column($result, 'periodeReference');
    }
}
