<?php

namespace App\Repository;

use App\Entity\Invitation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Invitation>
 */
class InvitationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Invitation::class);
    }

    public function findByToken(string $token): ?Invitation
    {
        return $this->findOneBy(['token' => $token]);
    }

    public function findValidByToken(string $token): ?Invitation
    {
        $invitation = $this->findByToken($token);

        if ($invitation && $invitation->isValid()) {
            return $invitation;
        }

        return null;
    }

    public function findExpiredInvitations(): array
    {
        return $this->createQueryBuilder('i')
            ->where('i.status = :pending')
            ->andWhere('i.expiresAt < :now')
            ->setParameter('pending', Invitation::STATUS_PENDING)
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->getResult();
    }

    public function findPendingInvitationsAboutToExpire(int $daysBeforeExpiry = 2): array
    {
        $startDate = new \DateTime();
        $endDate = (new \DateTime())->modify("+{$daysBeforeExpiry} days");

        return $this->createQueryBuilder('i')
            ->where('i.status = :pending')
            ->andWhere('i.expiresAt BETWEEN :start AND :end')
            ->setParameter('pending', Invitation::STATUS_PENDING)
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->getQuery()
            ->getResult();
    }
}
