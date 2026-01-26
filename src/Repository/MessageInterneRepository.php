<?php

namespace App\Repository;

use App\Entity\MessageInterne;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MessageInterne>
 */
class MessageInterneRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MessageInterne::class);
    }

    /**
     * Find received messages for user (inbox)
     *
     * @return MessageInterne[]
     */
    public function findReceivedByUser(User $user, int $page = 1, int $limit = 20): array
    {
        return $this->createQueryBuilder('m')
            ->join('m.destinataires', 'd')
            ->where('d = :user')
            ->setParameter('user', $user)
            ->orderBy('m.dateEnvoi', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Count received messages for user
     */
    public function countReceivedByUser(User $user): int
    {
        return (int) $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->join('m.destinataires', 'd')
            ->where('d = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Find sent messages by user
     *
     * @return MessageInterne[]
     */
    public function findSentByUser(User $user, int $page = 1, int $limit = 20): array
    {
        return $this->createQueryBuilder('m')
            ->where('m.expediteur = :user')
            ->setParameter('user', $user)
            ->orderBy('m.dateEnvoi', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Count sent messages by user
     */
    public function countSentByUser(User $user): int
    {
        return (int) $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->where('m.expediteur = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Count unread messages for user
     */
    public function countUnreadForUser(User $user): int
    {
        $messages = $this->createQueryBuilder('m')
            ->join('m.destinataires', 'd')
            ->where('d = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();

        $count = 0;
        foreach ($messages as $message) {
            if (!$message->isReadBy($user)) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Find messages older than X months for purge (R4: 12 months)
     *
     * @return MessageInterne[]
     */
    public function findOlderThan(\DateTimeInterface $date): array
    {
        return $this->createQueryBuilder('m')
            ->where('m.dateEnvoi < :date')
            ->setParameter('date', $date)
            ->getQuery()
            ->getResult();
    }

    /**
     * Delete messages older than given date
     */
    public function deleteOlderThan(\DateTimeInterface $date): int
    {
        // First, remove join table entries
        $messages = $this->findOlderThan($date);
        $count = count($messages);

        $em = $this->getEntityManager();
        foreach ($messages as $message) {
            $em->remove($message);
        }
        $em->flush();

        return $count;
    }

    /**
     * Find recent conversations for a user
     *
     * @return MessageInterne[]
     */
    public function findRecentConversations(User $user, int $limit = 5): array
    {
        // Get both sent and received messages, ordered by date
        $qb = $this->createQueryBuilder('m')
            ->leftJoin('m.destinataires', 'd')
            ->where('m.expediteur = :user')
            ->orWhere('d = :user')
            ->setParameter('user', $user)
            ->orderBy('m.dateEnvoi', 'DESC')
            ->setMaxResults($limit);

        return $qb->getQuery()->getResult();
    }
}
