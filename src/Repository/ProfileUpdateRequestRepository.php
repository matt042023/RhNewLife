<?php

namespace App\Repository;

use App\Entity\ProfileUpdateRequest;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProfileUpdateRequest>
 */
class ProfileUpdateRequestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProfileUpdateRequest::class);
    }

    /**
     * Trouve toutes les demandes en attente
     */
    public function findPending(): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.status = :status')
            ->setParameter('status', ProfileUpdateRequest::STATUS_PENDING)
            ->orderBy('p.requestedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les demandes d'un utilisateur
     */
    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.user = :user')
            ->setParameter('user', $user)
            ->orderBy('p.requestedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte les demandes en attente
     */
    public function countPending(): int
    {
        return $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.status = :status')
            ->setParameter('status', ProfileUpdateRequest::STATUS_PENDING)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Trouve les demandes récentes (derniers 30 jours)
     */
    public function findRecent(int $days = 30): array
    {
        $cutoffDate = new \DateTime("-{$days} days");

        return $this->createQueryBuilder('p')
            ->where('p.requestedAt >= :cutoffDate')
            ->setParameter('cutoffDate', $cutoffDate)
            ->orderBy('p.requestedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
