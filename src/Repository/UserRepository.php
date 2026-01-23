<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    /**
     * Trouve les éducateurs (utilisateurs non-admin) pour les modules de paie
     * Inclut les utilisateurs actifs et onboarding qui peuvent recevoir des éléments de paie
     *
     * @return User[]
     */
    public function findActiveEducators(): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.status IN (:statuses)')
            ->andWhere('u.roles NOT LIKE :adminRole')
            ->setParameter('statuses', [User::STATUS_ACTIVE, User::STATUS_ONBOARDING])
            ->setParameter('adminRole', '%ROLE_ADMIN%')
            ->orderBy('u.lastName', 'ASC')
            ->addOrderBy('u.firstName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les utilisateurs par rôle (actifs uniquement)
     *
     * @return User[]
     */
    public function findByRole(string $role): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.roles LIKE :role')
            ->andWhere('u.status = :status')
            ->setParameter('role', '%' . $role . '%')
            ->setParameter('status', User::STATUS_ACTIVE)
            ->orderBy('u.lastName', 'ASC')
            ->addOrderBy('u.firstName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve tous les utilisateurs actifs
     *
     * @return User[]
     */
    public function findAllActive(): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.status = :status')
            ->setParameter('status', User::STATUS_ACTIVE)
            ->orderBy('u.lastName', 'ASC')
            ->addOrderBy('u.firstName', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
