<?php

namespace App\Repository;

use App\Entity\Document;
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
        return $this->findOneBy(['user' => $user, 'type' => $type]);
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
}
