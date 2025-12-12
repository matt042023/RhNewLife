<?php

namespace App\Repository;

use App\Entity\AppointmentParticipant;
use App\Entity\RendezVous;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AppointmentParticipant>
 *
 * @method AppointmentParticipant|null find($id, $lockMode = null, $lockVersion = null)
 * @method AppointmentParticipant|null findOneBy(array $criteria, array $orderBy = null)
 * @method AppointmentParticipant[]    findAll()
 * @method AppointmentParticipant[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class AppointmentParticipantRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AppointmentParticipant::class);
    }

    public function save(AppointmentParticipant $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(AppointmentParticipant $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Trouve tous les participants d'un rendez-vous
     *
     * @return AppointmentParticipant[]
     */
    public function findByAppointment(RendezVous $appointment): array
    {
        return $this->createQueryBuilder('ap')
            ->andWhere('ap.appointment = :appointment')
            ->setParameter('appointment', $appointment)
            ->orderBy('ap.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve tous les rendez-vous d'un utilisateur
     *
     * @return AppointmentParticipant[]
     */
    public function findByUser(User $user, ?string $status = null): array
    {
        $qb = $this->createQueryBuilder('ap')
            ->innerJoin('ap.appointment', 'a')
            ->andWhere('ap.user = :user')
            ->setParameter('user', $user)
            ->orderBy('a.startAt', 'DESC');

        if ($status !== null) {
            $qb->andWhere('ap.presenceStatus = :status')
                ->setParameter('status', $status);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve les participations en attente de confirmation pour un utilisateur
     *
     * @return AppointmentParticipant[]
     */
    public function findPendingForUser(User $user): array
    {
        return $this->createQueryBuilder('ap')
            ->innerJoin('ap.appointment', 'a')
            ->andWhere('ap.user = :user')
            ->andWhere('ap.presenceStatus = :pending')
            ->andWhere('a.statut = :confirme')
            ->andWhere('a.startAt > :now')
            ->setParameter('user', $user)
            ->setParameter('pending', AppointmentParticipant::PRESENCE_PENDING)
            ->setParameter('confirme', RendezVous::STATUS_CONFIRME)
            ->setParameter('now', new \DateTime())
            ->orderBy('a.startAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte le nombre de participants confirmés pour un rendez-vous
     */
    public function countConfirmedByAppointment(RendezVous $appointment): int
    {
        return (int) $this->createQueryBuilder('ap')
            ->select('COUNT(ap.id)')
            ->andWhere('ap.appointment = :appointment')
            ->andWhere('ap.presenceStatus = :confirmed')
            ->setParameter('appointment', $appointment)
            ->setParameter('confirmed', AppointmentParticipant::PRESENCE_CONFIRMED)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
