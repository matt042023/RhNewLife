<?php

namespace App\Repository;

use App\Entity\SqueletteGarde;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SqueletteGarde>
 */
class SqueletteGardeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SqueletteGarde::class);
    }

    public function save(SqueletteGarde $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(SqueletteGarde $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find all templates
     *
     * @return SqueletteGarde[]
     */
    public function findAllTemplates(): array
    {
        return $this->createQueryBuilder('s')
            ->orderBy('s.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get templates with usage statistics
     *
     * @return array
     */
    public function getStats(): array
    {
        $templates = $this->findAllTemplates();

        return [
            'total' => count($templates),
            'totalUsage' => array_sum(array_map(fn($t) => $t->getNombreUtilisations(), $templates)),
        ];
    }

    /**
     * Check if a template name already exists
     */
    public function nameExists(string $nom, ?int $excludeId = null): bool
    {
        $qb = $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->where('s.nom = :nom')
            ->setParameter('nom', $nom);

        if ($excludeId) {
            $qb->andWhere('s.id != :excludeId')
               ->setParameter('excludeId', $excludeId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }

    /**
     * Find most recently used templates for quick access
     *
     * @return SqueletteGarde[]
     */
    public function findRecentlyUsed(int $limit = 5): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.derniereUtilisation IS NOT NULL')
            ->orderBy('s.derniereUtilisation', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
