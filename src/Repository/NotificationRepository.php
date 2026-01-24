<?php

namespace App\Repository;

use App\Entity\Notification;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Notification>
 */
class NotificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Notification::class);
    }

    /**
     * Get unread notifications for user (R2: user sees only their notifications)
     *
     * @return Notification[]
     */
    public function findUnreadForUser(User $user, int $limit = 10): array
    {
        return $this->createQueryBuilder('n')
            ->where('n.cibleUser = :user')
            ->andWhere('n.lu = false')
            ->setParameter('user', $user)
            ->orderBy('n.dateEnvoi', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get all notifications for user with pagination
     *
     * @return Notification[]
     */
    public function findForUserPaginated(User $user, int $page = 1, int $limit = 20): array
    {
        return $this->createQueryBuilder('n')
            ->where('n.cibleUser = :user')
            ->setParameter('user', $user)
            ->orderBy('n.dateEnvoi', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Count total notifications for user (for pagination)
     */
    public function countForUser(User $user): int
    {
        return (int) $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->where('n.cibleUser = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Count unread for badge
     */
    public function countUnreadForUser(User $user): int
    {
        return (int) $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->where('n.cibleUser = :user')
            ->andWhere('n.lu = false')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Check for duplicate notification (R1: non dupliquees)
     */
    public function existsForSourceEvent(string $sourceEvent, int $sourceEntityId, User $user): bool
    {
        return (bool) $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->where('n.sourceEvent = :event')
            ->andWhere('n.sourceEntityId = :entityId')
            ->andWhere('n.cibleUser = :user')
            ->setParameter('event', $sourceEvent)
            ->setParameter('entityId', $sourceEntityId)
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Find notifications older than X days for purge (WF76: 60 days)
     *
     * @return Notification[]
     */
    public function findOlderThan(\DateTimeInterface $date): array
    {
        return $this->createQueryBuilder('n')
            ->where('n.dateEnvoi < :date')
            ->setParameter('date', $date)
            ->getQuery()
            ->getResult();
    }

    /**
     * Delete notifications older than X days
     */
    public function deleteOlderThan(\DateTimeInterface $date): int
    {
        return $this->createQueryBuilder('n')
            ->delete()
            ->where('n.dateEnvoi < :date')
            ->setParameter('date', $date)
            ->getQuery()
            ->execute();
    }

    /**
     * Mark all notifications as read for user
     */
    public function markAllAsReadForUser(User $user): int
    {
        return $this->createQueryBuilder('n')
            ->update()
            ->set('n.lu', ':lu')
            ->set('n.luAt', ':luAt')
            ->where('n.cibleUser = :user')
            ->andWhere('n.lu = false')
            ->setParameter('lu', true)
            ->setParameter('luAt', new \DateTime())
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();
    }
}
