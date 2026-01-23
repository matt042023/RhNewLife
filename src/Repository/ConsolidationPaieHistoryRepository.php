<?php

namespace App\Repository;

use App\Entity\ConsolidationPaie;
use App\Entity\ConsolidationPaieHistory;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ConsolidationPaieHistory>
 */
class ConsolidationPaieHistoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ConsolidationPaieHistory::class);
    }

    /**
     * Trouve l'historique d'une consolidation
     *
     * @return ConsolidationPaieHistory[]
     */
    public function findByConsolidation(ConsolidationPaie $consolidation): array
    {
        return $this->createQueryBuilder('h')
            ->andWhere('h.consolidation = :consolidation')
            ->setParameter('consolidation', $consolidation)
            ->orderBy('h.modifiedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve l'historique par type d'action
     *
     * @return ConsolidationPaieHistory[]
     */
    public function findByConsolidationAndAction(ConsolidationPaie $consolidation, string $action): array
    {
        return $this->createQueryBuilder('h')
            ->andWhere('h.consolidation = :consolidation')
            ->andWhere('h.action = :action')
            ->setParameter('consolidation', $consolidation)
            ->setParameter('action', $action)
            ->orderBy('h.modifiedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les modifications effectuées par un utilisateur
     *
     * @return ConsolidationPaieHistory[]
     */
    public function findByModifiedBy(User $user, ?int $limit = null): array
    {
        $qb = $this->createQueryBuilder('h')
            ->andWhere('h.modifiedBy = :user')
            ->setParameter('user', $user)
            ->orderBy('h.modifiedAt', 'DESC');

        if ($limit !== null) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve les corrections effectuées sur une consolidation
     *
     * @return ConsolidationPaieHistory[]
     */
    public function findCorrections(ConsolidationPaie $consolidation): array
    {
        return $this->findByConsolidationAndAction($consolidation, ConsolidationPaieHistory::ACTION_CORRECTION);
    }

    /**
     * Compte le nombre de modifications par action pour une consolidation
     *
     * @return array<string, int>
     */
    public function countByAction(ConsolidationPaie $consolidation): array
    {
        $result = $this->createQueryBuilder('h')
            ->select('h.action, COUNT(h.id) as count')
            ->andWhere('h.consolidation = :consolidation')
            ->setParameter('consolidation', $consolidation)
            ->groupBy('h.action')
            ->getQuery()
            ->getResult();

        $counts = [];
        foreach ($result as $row) {
            $counts[$row['action']] = (int) $row['count'];
        }

        return $counts;
    }

    /**
     * Trouve les dernières modifications globales (pour tableau de bord admin)
     *
     * @return ConsolidationPaieHistory[]
     */
    public function findLatest(int $limit = 20): array
    {
        return $this->createQueryBuilder('h')
            ->orderBy('h.modifiedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
