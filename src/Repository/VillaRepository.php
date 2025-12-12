<?php

namespace App\Repository;

use App\Entity\Villa;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Villa>
 *
 * @method Villa|null find($id, $lockMode = null, $lockVersion = null)
 * @method Villa|null findOneBy(array $criteria, array $orderBy = null)
 * @method Villa[]    findAll()
 * @method Villa[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class VillaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Villa::class);
    }

    public function save(Villa $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Villa $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
