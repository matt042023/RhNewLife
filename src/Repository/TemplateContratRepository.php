<?php

namespace App\Repository;

use App\Entity\TemplateContrat;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TemplateContrat>
 */
class TemplateContratRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TemplateContrat::class);
    }

    /**
     * Trouve les templates actifs
     */
    public function findActiveTemplates(): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.active = :active')
            ->setParameter('active', true)
            ->orderBy('t.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche templates par nom
     */
    public function searchByName(string $query): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.name LIKE :query')
            ->orWhere('t.description LIKE :query')
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('t.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte le nombre de contrats utilisant ce template
     */
    public function countContractsUsingTemplate(TemplateContrat $template): int
    {
        return $this->createQueryBuilder('t')
            ->select('COUNT(c.id)')
            ->leftJoin('t.contracts', 'c')
            ->where('t.id = :template')
            ->setParameter('template', $template)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Trouve tous les templates avec le nombre de contrats associés
     */
    public function findAllWithContractCount(): array
    {
        return $this->createQueryBuilder('t')
            ->select('t', 'COUNT(c.id) as contractCount')
            ->leftJoin('t.contracts', 'c')
            ->groupBy('t.id')
            ->orderBy('t.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Sauvegarde un template
     */
    public function save(TemplateContrat $template, bool $flush = false): void
    {
        $this->getEntityManager()->persist($template);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Supprime un template (soft delete - désactivation)
     */
    public function remove(TemplateContrat $template, bool $flush = false): void
    {
        $template->setActive(false);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
