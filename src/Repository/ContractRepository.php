<?php

namespace App\Repository;

use App\Entity\Contract;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Contract>
 */
class ContractRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Contract::class);
    }

    /**
     * Trouve le contrat actif d'un utilisateur
     */
    public function findActiveContract(User $user): ?Contract
    {
        return $this->createQueryBuilder('c')
            ->where('c.user = :user')
            ->andWhere('c.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', Contract::STATUS_ACTIVE)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouve tous les contrats d'un utilisateur (historique complet)
     */
    public function findAllByUser(User $user): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.user = :user')
            ->setParameter('user', $user)
            ->orderBy('c.version', 'DESC')
            ->addOrderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Vérifie si un utilisateur a déjà un contrat actif
     */
    public function hasActiveContract(User $user): bool
    {
        return $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.user = :user')
            ->andWhere('c.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', Contract::STATUS_ACTIVE)
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }

    /**
     * Trouve les contrats qui arrivent à expiration (pour alertes)
     */
    public function findExpiringContracts(int $daysBeforeExpiry = 30): array
    {
        $targetDate = new \DateTime("+{$daysBeforeExpiry} days");

        return $this->createQueryBuilder('c')
            ->where('c.status IN (:statuses)')
            ->andWhere('c.endDate IS NOT NULL')
            ->andWhere('c.endDate <= :targetDate')
            ->andWhere('c.endDate >= :now')
            ->setParameter('statuses', [Contract::STATUS_ACTIVE, Contract::STATUS_SIGNED])
            ->setParameter('targetDate', $targetDate)
            ->setParameter('now', new \DateTime())
            ->orderBy('c.endDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les contrats dont la période d'essai arrive à expiration
     */
    public function findExpiringEssai(int $daysBeforeExpiry = 15): array
    {
        $targetDate = new \DateTime("+{$daysBeforeExpiry} days");

        return $this->createQueryBuilder('c')
            ->where('c.status IN (:statuses)')
            ->andWhere('c.essaiEndDate IS NOT NULL')
            ->andWhere('c.essaiEndDate <= :targetDate')
            ->andWhere('c.essaiEndDate >= :now')
            ->setParameter('statuses', [Contract::STATUS_ACTIVE, Contract::STATUS_SIGNED])
            ->setParameter('targetDate', $targetDate)
            ->setParameter('now', new \DateTime())
            ->orderBy('c.essaiEndDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte les contrats actifs par type
     */
    public function countByType(): array
    {
        $results = $this->createQueryBuilder('c')
            ->select('c.type', 'COUNT(c.id) as count')
            ->where('c.status IN (:statuses)')
            ->setParameter('statuses', [Contract::STATUS_ACTIVE, Contract::STATUS_SIGNED])
            ->groupBy('c.type')
            ->getQuery()
            ->getResult();

        $counts = [];
        foreach ($results as $result) {
            $counts[$result['type']] = (int) $result['count'];
        }

        return $counts;
    }

    /**
     * Trouve les contrats brouillon non finalisés depuis plus de X jours
     */
    public function findStaleDrafts(int $daysOld = 30): array
    {
        $cutoffDate = new \DateTime("-{$daysOld} days");

        return $this->createQueryBuilder('c')
            ->where('c.status = :status')
            ->andWhere('c.createdAt <= :cutoffDate')
            ->setParameter('status', Contract::STATUS_DRAFT)
            ->setParameter('cutoffDate', $cutoffDate)
            ->orderBy('c.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
