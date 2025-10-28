<?php

namespace App\Repository;

use App\Entity\Document;
use App\Entity\Contract;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Document>
 */
class DocumentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Document::class);
    }

    public function findByUser(User $user): array
    {
        return $this->findBy(['user' => $user], ['uploadedAt' => 'DESC']);
    }

    public function findByUserAndType(User $user, string $type): ?Document
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.user = :user')
            ->andWhere('d.type = :type')
            ->andWhere('d.archived = false')
            ->setParameter('user', $user)
            ->setParameter('type', $type)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findActiveByUser(User $user): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.user = :user')
            ->andWhere('d.archived = false')
            ->setParameter('user', $user)
            ->orderBy('d.uploadedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findArchivedByUser(User $user): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.user = :user')
            ->andWhere('d.archived = true')
            ->setParameter('user', $user)
            ->orderBy('d.archivedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findExpiredArchives(\DateTimeImmutable $referenceDate): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.archived = true')
            ->andWhere('d.archivedAt IS NOT NULL')
            ->andWhere('d.retentionYears IS NOT NULL')
            ->andWhere('DATE_ADD(d.archivedAt, d.retentionYears, \'year\') <= :now')
            ->setParameter('now', $referenceDate)
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche des documents pour l'interface admin avec filtres.
     *
     * @param array{
     *     status?: string|null,
     *     type?: string|null,
     *     archived?: bool|null,
     *     user?: int|null,
     *     search?: string|null,
     *     from?: \DateTimeImmutable|null,
     *     to?: \DateTimeImmutable|null,
     *     limit?: int|null,
     *     min_size?: int|null,
     *     max_size?: int|null
     * } $filters
     *
     * @return Document[]
     */
    public function searchForAdmin(array $filters = []): array
    {
        $qb = $this->createQueryBuilder('d')
            ->leftJoin('d.user', 'u')
            ->addSelect('u')
            ->orderBy('d.uploadedAt', 'DESC');

        if (!empty($filters['status'])) {
            $qb->andWhere('d.status = :status')
               ->setParameter('status', $filters['status']);
        }

        if (!empty($filters['type'])) {
            $qb->andWhere('d.type = :type')
               ->setParameter('type', $filters['type']);
        }

        if (array_key_exists('archived', $filters) && $filters['archived'] !== null) {
            $qb->andWhere('d.archived = :archived')
               ->setParameter('archived', (bool) $filters['archived']);
        }

        if (!empty($filters['user'])) {
            $qb->andWhere('u.id = :userId')
               ->setParameter('userId', (int) $filters['user']);
        }

        if (!empty($filters['search'])) {
            $qb->andWhere('d.originalName LIKE :search OR d.comment LIKE :search')
               ->setParameter('search', '%' . $filters['search'] . '%');
        }

        if (!empty($filters['from']) && $filters['from'] instanceof \DateTimeImmutable) {
            $qb->andWhere('d.uploadedAt >= :from')
               ->setParameter('from', $filters['from']);
        }

        if (!empty($filters['to']) && $filters['to'] instanceof \DateTimeImmutable) {
            $qb->andWhere('d.uploadedAt <= :to')
               ->setParameter('to', $filters['to']);
        }

        if (!empty($filters['min_size'])) {
            $qb->andWhere('d.fileSize >= :minSize')
               ->setParameter('minSize', (int) $filters['min_size']);
        }

        if (!empty($filters['max_size'])) {
            $qb->andWhere('d.fileSize <= :maxSize')
               ->setParameter('maxSize', (int) $filters['max_size']);
        }

        if (!empty($filters['limit'])) {
            $qb->setMaxResults((int) $filters['limit']);
        }

        return $qb->getQuery()->getResult();
    }

    public function getUserDocumentCompletionStatus(User $user): array
    {
        $documents = $this->findByUser($user);
        $documentsByType = [];

        foreach ($documents as $document) {
            $documentsByType[$document->getType()] = $document;
        }

        $requiredDocuments = Document::REQUIRED_DOCUMENTS;
        $totalRequired = count($requiredDocuments);
        $completed = 0;
        $status = [];

        foreach ($requiredDocuments as $type) {
            $hasDocument = isset($documentsByType[$type]);
            $status[$type] = [
                'uploaded' => $hasDocument,
                'document' => $hasDocument ? $documentsByType[$type] : null,
                'label' => Document::TYPES[$type] ?? $type,
            ];

            if ($hasDocument) {
                $completed++;
            }
        }

        return [
            'total' => $totalRequired,
            'completed' => $completed,
            'percentage' => $totalRequired > 0 ? ($completed / $totalRequired) * 100 : 0,
            'is_complete' => $completed === $totalRequired,
            'documents' => $status,
        ];
    }

    public function findPendingDocuments(): array
    {
        return $this->findBy(['status' => Document::STATUS_PENDING], ['uploadedAt' => 'DESC']);
    }

    public function findValidatedDocumentsByUser(User $user): array
    {
        return $this->findBy(
            ['user' => $user, 'status' => Document::STATUS_VALIDATED],
            ['uploadedAt' => 'DESC']
        );
    }

    public function findSignedContractDocument(Contract $contract): ?Document
    {
        return $this->findOneBy(
            ['contract' => $contract, 'type' => Document::TYPE_CONTRACT_SIGNED],
            ['uploadedAt' => 'DESC']
        );
    }
}
