<?php

namespace App\Repository;

use App\Entity\RendezVous;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RendezVous>
 *
 * @method RendezVous|null find($id, $lockMode = null, $lockVersion = null)
 * @method RendezVous|null findOneBy(array $criteria, array $orderBy = null)
 * @method RendezVous[]    findAll()
 * @method RendezVous[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class RendezVousRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RendezVous::class);
    }

    public function save(RendezVous $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(RendezVous $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Trouve les demandes de rendez-vous en attente de validation
     *
     * @return RendezVous[]
     */
    public function findPendingRequests(): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.type = :type')
            ->andWhere('r.statut = :statut')
            ->setParameter('type', RendezVous::TYPE_DEMANDE)
            ->setParameter('statut', RendezVous::STATUS_EN_ATTENTE)
            ->orderBy('r.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les rendez-vous organisés par un utilisateur
     *
     * @return RendezVous[]
     */
    public function findByOrganizer(User $organizer, ?array $filters = []): array
    {
        $qb = $this->createQueryBuilder('r')
            ->andWhere('r.organizer = :organizer')
            ->setParameter('organizer', $organizer)
            ->orderBy('r.startAt', 'DESC');

        if (isset($filters['status'])) {
            $qb->andWhere('r.statut = :status')
                ->setParameter('status', $filters['status']);
        }

        if (isset($filters['type'])) {
            $qb->andWhere('r.type = :type')
                ->setParameter('type', $filters['type']);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve les rendez-vous où un utilisateur est participant
     *
     * @return RendezVous[]
     */
    public function findByParticipant(User $participant, ?array $filters = []): array
    {
        $qb = $this->createQueryBuilder('r')
            ->innerJoin('r.appointmentParticipants', 'ap')
            ->andWhere('ap.user = :participant')
            ->setParameter('participant', $participant)
            ->orderBy('r.startAt', 'DESC');

        if (isset($filters['status'])) {
            $qb->andWhere('r.statut = :status')
                ->setParameter('status', $filters['status']);
        }

        if (isset($filters['type'])) {
            $qb->andWhere('r.type = :type')
                ->setParameter('type', $filters['type']);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve les rendez-vous à venir pour un utilisateur
     *
     * @return RendezVous[]
     */
    public function findUpcomingForUser(User $user): array
    {
        return $this->createQueryBuilder('r')
            ->innerJoin('r.appointmentParticipants', 'ap')
            ->andWhere('ap.user = :user OR r.organizer = :user')
            ->andWhere('r.startAt > :now')
            ->andWhere('r.statut IN (:statuses)')
            ->setParameter('user', $user)
            ->setParameter('now', new \DateTime())
            ->setParameter('statuses', [RendezVous::STATUS_CONFIRME, RendezVous::STATUS_EN_ATTENTE])
            ->orderBy('r.startAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve l'historique des rendez-vous pour un utilisateur
     *
     * @return RendezVous[]
     */
    public function findHistoryForUser(User $user): array
    {
        return $this->createQueryBuilder('r')
            ->innerJoin('r.appointmentParticipants', 'ap')
            ->andWhere('ap.user = :user OR r.organizer = :user')
            ->andWhere('r.startAt <= :now OR r.statut IN (:statuses)')
            ->setParameter('user', $user)
            ->setParameter('now', new \DateTime())
            ->setParameter('statuses', [RendezVous::STATUS_TERMINE, RendezVous::STATUS_ANNULE, RendezVous::STATUS_REFUSE])
            ->orderBy('r.startAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les rendez-vous qui se chevauchent avec une période donnée pour un utilisateur
     *
     * @return RendezVous[]
     */
    public function findConflictingAppointments(
        User $user,
        \DateTimeInterface $start,
        \DateTimeInterface $end,
        ?int $excludeId = null
    ): array {
        $qb = $this->createQueryBuilder('r')
            ->innerJoin('r.appointmentParticipants', 'ap')
            ->andWhere('ap.user = :user')
            ->andWhere('r.statut = :confirme')
            ->andWhere('(r.startAt < :end AND r.endAt > :start)')
            ->setParameter('user', $user)
            ->setParameter('confirme', RendezVous::STATUS_CONFIRME)
            ->setParameter('start', $start)
            ->setParameter('end', $end);

        if ($excludeId !== null) {
            $qb->andWhere('r.id != :excludeId')
                ->setParameter('excludeId', $excludeId);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve tous les rendez-vous avec filtres pour l'admin
     *
     * @return RendezVous[]
     */
    public function findAllWithFilters(array $filters): array
    {
        $qb = $this->createQueryBuilder('r')
            ->orderBy('r.startAt', 'DESC');

        if (isset($filters['status'])) {
            $qb->andWhere('r.statut = :status')
                ->setParameter('status', $filters['status']);
        }

        if (isset($filters['type'])) {
            $qb->andWhere('r.type = :type')
                ->setParameter('type', $filters['type']);
        }

        if (isset($filters['dateFrom'])) {
            $qb->andWhere('r.startAt >= :dateFrom')
                ->setParameter('dateFrom', $filters['dateFrom']);
        }

        if (isset($filters['dateTo'])) {
            $qb->andWhere('r.startAt <= :dateTo')
                ->setParameter('dateTo', $filters['dateTo']);
        }

        if (isset($filters['participant'])) {
            $qb->innerJoin('r.appointmentParticipants', 'ap')
                ->andWhere('ap.user = :participant')
                ->setParameter('participant', $filters['participant']);
        }

        return $qb->getQuery()->getResult();
    }
}
