<?php

namespace App\Service;

use App\Entity\Document;
use App\Entity\User;
use App\Repository\DocumentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;
use Psr\Log\LoggerInterface;

class DocumentManager
{
    private const MAX_FILE_SIZE = 5 * 1024 * 1024; // 5 Mo
    private const ALLOWED_MIME_TYPES = [
        'application/pdf',
        'image/jpeg',
        'image/jpg',
        'image/png',
    ];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private DocumentRepository $documentRepository,
        private SluggerInterface $slugger,
        private LoggerInterface $logger,
        private string $uploadsDirectory
    ) {
    }

    /**
     * Upload un document pour un utilisateur
     */
    public function uploadDocument(
        UploadedFile $file,
        User $user,
        string $type,
        ?string $comment = null
    ): Document {
        // Validations
        $this->validateFile($file);

        // Capture les métadonnées du fichier AVANT de le déplacer
        $originalName = $file->getClientOriginalName();
        $mimeType = $file->getClientMimeType() ?? 'application/octet-stream';
        $fileSize = $file->getSize() ?? 0;

        // Vérifie si un document du même type existe déjà
        $existingDocument = $this->documentRepository->findByUserAndType($user, $type);
        if ($existingDocument) {
            // Supprime l'ancien fichier
            $this->deleteFile($existingDocument);
            $this->entityManager->remove($existingDocument);
        }

        // Génère un nom de fichier unique
        $originalFilename = pathinfo($originalName, PATHINFO_FILENAME);
        $safeFilename = $this->slugger->slug($originalFilename);
        $newFilename = $safeFilename . '-' . uniqid() . '.' . $file->guessExtension();

        // Crée le répertoire utilisateur si nécessaire
        $userDirectory = $this->getUserDirectory($user);
        if (!is_dir($userDirectory)) {
            mkdir($userDirectory, 0755, true);
        }

        // Déplace le fichier
        try {
            $file->move($userDirectory, $newFilename);
        } catch (FileException $e) {
            $this->logger->error('Failed to upload file', [
                'error' => $e->getMessage(),
                'user_id' => $user->getId(),
            ]);

            throw new \RuntimeException('Erreur lors de l\'upload du fichier : ' . $e->getMessage());
        }

        // Crée l'entité Document avec les métadonnées capturées
        $document = new Document();
        $document
            ->setUser($user)
            ->setType($type)
            ->setFileName($newFilename)
            ->setOriginalName($originalName)
            ->setMimeType($mimeType)
            ->setFileSize($fileSize)
            ->setComment($comment);

        $this->entityManager->persist($document);
        $this->entityManager->flush();

        $this->logger->info('Document uploaded', [
            'document_id' => $document->getId(),
            'user_id' => $user->getId(),
            'type' => $type,
        ]);

        return $document;
    }

    /**
     * Supprime un document
     */
    public function deleteDocument(Document $document): void
    {
        $this->deleteFile($document);
        $this->entityManager->remove($document);
        $this->entityManager->flush();

        $this->logger->info('Document deleted', [
            'document_id' => $document->getId(),
            'user_id' => $document->getUser()->getId(),
        ]);
    }

    /**
     * Valide un document
     */
    public function validateDocument(Document $document, ?string $comment = null): void
    {
        $document->markAsValidated($comment);
        $this->entityManager->flush();

        $this->logger->info('Document validated', [
            'document_id' => $document->getId(),
        ]);
    }

    /**
     * Rejette un document
     */
    public function rejectDocument(Document $document, string $reason): void
    {
        $document->markAsRejected($reason);
        $this->entityManager->flush();

        $this->logger->info('Document rejected', [
            'document_id' => $document->getId(),
            'reason' => $reason,
        ]);
    }

    /**
     * Récupère le statut de complétion des documents pour un utilisateur
     */
    public function getCompletionStatus(User $user): array
    {
        return $this->documentRepository->getUserDocumentCompletionStatus($user);
    }

    /**
     * Vérifie si tous les documents obligatoires sont uploadés
     */
    public function hasAllRequiredDocuments(User $user): bool
    {
        $status = $this->getCompletionStatus($user);
        return $status['is_complete'];
    }

    /**
     * Récupère le chemin complet d'un document
     */
    public function getDocumentPath(Document $document): string
    {
        return $this->getUserDirectory($document->getUser()) . '/' . $document->getFileName();
    }

    /**
     * Valide un fichier uploadé
     */
    private function validateFile(UploadedFile $file): void
    {
        // Vérifier que le fichier est valide (uploadé sans erreur)
        if (!$file->isValid()) {
            throw new \InvalidArgumentException('Le fichier n\'a pas été uploadé correctement.');
        }

        $errors = [];

        // Taille du fichier - utiliser getClientSize() au lieu de getSize()
        $fileSize = $file->getSize();
        if ($fileSize && $fileSize > self::MAX_FILE_SIZE) {
            $errors[] = sprintf(
                'Le fichier est trop volumineux (max %d Mo).',
                self::MAX_FILE_SIZE / 1024 / 1024
            );
        }

        // Type MIME - utiliser getClientMimeType() pour éviter d'accéder au fichier temporaire
        $mimeType = $file->getClientMimeType();
        if ($mimeType && !in_array($mimeType, self::ALLOWED_MIME_TYPES)) {
            $errors[] = 'Type de fichier non autorisé. Formats acceptés : PDF, JPG, PNG.';
        }

        if (!empty($errors)) {
            throw new \InvalidArgumentException(implode(' ', $errors));
        }
    }

    /**
     * Récupère le répertoire de stockage pour un utilisateur
     */
    private function getUserDirectory(User $user): string
    {
        return $this->uploadsDirectory . '/users/' . $user->getId();
    }

    /**
     * Supprime physiquement un fichier
     */
    private function deleteFile(Document $document): void
    {
        $filePath = $this->getDocumentPath($document);

        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }
}
