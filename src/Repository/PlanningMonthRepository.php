<?php

namespace App\Repository;

use App\Entity\PlanningMonth;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PlanningMonth>
 *
 * @method PlanningMonth|null find($id, $lockMode = null, $lockVersion = null)
 * @method PlanningMonth|null findOneBy(array $criteria, array $orderBy = null)
 * @method PlanningMonth[]    findAll()
 * @method PlanningMonth[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PlanningMonthRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PlanningMonth::class);
    }

    public function save(PlanningMonth $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(PlanningMonth $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
