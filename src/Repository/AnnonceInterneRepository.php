<?php

namespace App\Repository;

use App\Entity\AnnonceInterne;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AnnonceInterne>
 */
class AnnonceInterneRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AnnonceInterne::class);
    }

    /**
     * Find active, non-expired announcements visible to user
     *
     * @return AnnonceInterne[]
     */
    public function findActiveForUser(User $user, int $limit = 5): array
    {
        $qb = $this->createQueryBuilder('a')
            ->where('a.actif = true')
            ->andWhere('a.dateExpiration IS NULL OR a.dateExpiration > :now')
            ->setParameter('now', new \DateTime())
            ->orderBy('a.epingle', 'DESC')
            ->addOrderBy('a.datePublication', 'DESC')
            ->setMaxResults($limit * 2); // Get extra to filter by visibility

        $results = $qb->getQuery()->getResult();

        // Filter by visibility in PHP (more flexible than SQL for role matching)
        $filtered = array_filter($results, fn($a) => $a->isVisibleToUser($user));

        return array_slice($filtered, 0, $limit);
    }

    /**
     * Find all active announcements (for admin dashboard widget)
     *
     * @return AnnonceInterne[]
     */
    public function findAllActive(int $limit = 10): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.actif = true')
            ->andWhere('a.dateExpiration IS NULL OR a.dateExpiration > :now')
            ->setParameter('now', new \DateTime())
            ->orderBy('a.epingle', 'DESC')
            ->addOrderBy('a.datePublication', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find pinned announcements
     *
     * @return AnnonceInterne[]
     */
    public function findPinned(): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.actif = true')
            ->andWhere('a.epingle = true')
            ->andWhere('a.dateExpiration IS NULL OR a.dateExpiration > :now')
            ->setParameter('now', new \DateTime())
            ->orderBy('a.datePublication', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find all for admin management with pagination
     *
     * @return AnnonceInterne[]
     */
    public function findAllPaginated(int $page = 1, int $limit = 20): array
    {
        return $this->createQueryBuilder('a')
            ->orderBy('a.datePublication', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Count all announcements (for pagination)
     */
    public function countAll(): int
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Find expired announcements for purge (R3: 30 days)
     *
     * @return AnnonceInterne[]
     */
    public function findExpired(): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.dateExpiration IS NOT NULL')
            ->andWhere('a.dateExpiration < :now')
            ->andWhere('a.actif = true')
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->getResult();
    }

    /**
     * Deactivate all expired announcements
     */
    public function deactivateExpired(): int
    {
        return $this->createQueryBuilder('a')
            ->update()
            ->set('a.actif', ':actif')
            ->where('a.dateExpiration IS NOT NULL')
            ->andWhere('a.dateExpiration < :now')
            ->andWhere('a.actif = true')
            ->setParameter('actif', false)
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->execute();
    }

    /**
     * Find announcements by visibility
     *
     * @return AnnonceInterne[]
     */
    public function findByVisibility(string $visibility, int $limit = 10): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.actif = true')
            ->andWhere('a.visibilite = :visibility')
            ->andWhere('a.dateExpiration IS NULL OR a.dateExpiration > :now')
            ->setParameter('visibility', $visibility)
            ->setParameter('now', new \DateTime())
            ->orderBy('a.epingle', 'DESC')
            ->addOrderBy('a.datePublication', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Search announcements by title or content
     *
     * @return AnnonceInterne[]
     */
    public function search(string $query, int $limit = 20): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.titre LIKE :query')
            ->orWhere('a.contenu LIKE :query')
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('a.datePublication', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
