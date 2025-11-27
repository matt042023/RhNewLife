<?php

namespace App\Repository;

use App\Entity\TypeAbsence;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TypeAbsence>
 */
class TypeAbsenceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TypeAbsence::class);
    }

    /**
     * Find all active absence types
     *
     * @return TypeAbsence[]
     */
    public function findActive(): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.active = :active')
            ->setParameter('active', true)
            ->orderBy('t.label', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find absence type by code
     */
    public function findByCode(string $code): ?TypeAbsence
    {
        return $this->createQueryBuilder('t')
            ->where('t.code = :code')
            ->setParameter('code', $code)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find all absence types that require justification
     *
     * @return TypeAbsence[]
     */
    public function findRequiringJustification(): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.requiresJustification = :required')
            ->andWhere('t.active = :active')
            ->setParameter('required', true)
            ->setParameter('active', true)
            ->orderBy('t.label', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find all absence types that affect planning
     *
     * @return TypeAbsence[]
     */
    public function findAffectingPlanning(): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.affectsPlanning = :affects')
            ->andWhere('t.active = :active')
            ->setParameter('affects', true)
            ->setParameter('active', true)
            ->orderBy('t.label', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
