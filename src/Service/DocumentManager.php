<?php

namespace App\Service;

use App\Entity\Contract;
use App\Entity\Document;
use App\Entity\User;
use App\Repository\DocumentRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

class DocumentManager
{
    private const MAX_FILE_SIZE = 10 * 1024 * 1024; // 10 Mo
    private const ALLOWED_MIME_TYPES = [
        'application/pdf',
        'image/jpeg',
        'image/jpg',
        'image/png',
    ];

    private const USER_FOLDER = 'users';
    private const ARCHIVE_FOLDER = 'archives';
    private const EXPORT_FOLDER = 'exports';

    private const RETENTION_DEFAULT = 5;

    private const RETENTION_POLICIES = [
        Document::TYPE_PAYSLIP => 5,
        Document::TYPE_ABSENCE_JUSTIFICATION => 3,
        Document::TYPE_MEDICAL_CERTIFICATE => 5,
        Document::TYPE_CNI => 5,
        Document::TYPE_RIB => 5,
        Document::TYPE_DOMICILE => 5,
    ];

    private const COMPLETION_CATEGORIES = [
        'identity' => [
            'label' => 'Identite',
            'required' => [
                Document::TYPE_CNI,
                Document::TYPE_DOMICILE,
            ],
            'optional' => [],
        ],
        'bank' => [
            'label' => 'Bancaire',
            'required' => [
                Document::TYPE_RIB,
            ],
            'optional' => [],
        ],
        'contract' => [
            'label' => 'Contrat',
            'required' => [
                Document::TYPE_CONTRAT,
                Document::TYPE_CONTRACT_SIGNED,
            ],
            'optional' => [
                ['type' => Document::TYPE_CONTRACT_AMENDMENT, 'priority' => 'medium'],
            ],
        ],
        'payroll' => [
            'label' => 'Paie',
            'required' => [],
            'optional' => [
                ['type' => Document::TYPE_PAYSLIP, 'priority' => 'low'],
            ],
        ],
        'medical' => [
            'label' => 'Medical',
            'required' => [],
            'optional' => [
                ['type' => Document::TYPE_MEDICAL_CERTIFICATE, 'priority' => 'medium'],
            ],
        ],
        'training' => [
            'label' => 'Formation',
            'required' => [],
            'optional' => [
                ['type' => Document::TYPE_TRAINING_CERTIFICATE, 'priority' => 'medium'],
            ],
        ],
        'absence' => [
            'label' => 'Absence',
            'required' => [],
            'optional' => [
                ['type' => Document::TYPE_ABSENCE_JUSTIFICATION, 'priority' => 'medium'],
            ],
        ],
        'expenses' => [
            'label' => 'Frais',
            'required' => [],
            'optional' => [
                ['type' => Document::TYPE_EXPENSE_REPORT, 'priority' => 'medium'],
            ],
        ],
        'other' => [
            'label' => 'Autres',
            'required' => [],
            'optional' => [
                ['type' => Document::TYPE_OTHER, 'priority' => 'low'],
            ],
        ],
    ];

    private const CONTRACT_OPTIONAL_DOCUMENTS = [
        Contract::TYPE_CDD => [
            'contract' => [
                Document::TYPE_CONTRACT_AMENDMENT,
            ],
        ],
        Contract::TYPE_STAGE => [
            'training' => [
                Document::TYPE_TRAINING_CERTIFICATE,
            ],
            'contract' => [
                Document::TYPE_WORK_CERTIFICATE,
            ],
        ],
        Contract::TYPE_ALTERNANCE => [
            'training' => [
                Document::TYPE_TRAINING_CERTIFICATE,
            ],
        ],
    ];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private DocumentRepository $documentRepository,
        private UserRepository $userRepository,
        private SluggerInterface $slugger,
        private LoggerInterface $logger,
        private MailerInterface $mailer,
        private UrlGeneratorInterface $urlGenerator,
        private string $uploadsDirectory
    ) {
    }

    public function uploadDocument(
        UploadedFile $file,
        User $user,
        string $type,
        ?string $comment = null
    ): Document {
        $this->validateFile($file);

        $existingDocument = $this->documentRepository->findByUserAndType($user, $type);
        if ($existingDocument) {
            return $this->replaceDocument($existingDocument, $file, $comment);
        }

        $document = $this->createDocumentFromUpload($file, $user, $type, $comment, 1);

        $this->entityManager->persist($document);
        $this->entityManager->flush();

        $this->logger->info('Document uploaded', [
            'document_id' => $document->getId(),
            'user_id' => $user->getId(),
            'user_name' => $user->getFullName(),
            'user_email' => $user->getEmail(),
            'type' => $type,
            'type_label' => $document->getTypeLabel(),
            'original_filename' => $document->getOriginalName(),
            'version' => $document->getVersion(),
            'size' => $document->getFileSize(),
        ]);

        return $document;
    }

    public function replaceDocument(Document $oldDocument, UploadedFile $newFile, ?string $comment = null): Document
    {
        $this->validateFile($newFile);

        $user = $oldDocument->getUser();
        $newVersion = $oldDocument->getVersion() + 1;

        $retentionYears = $this->resolveRetentionYears($oldDocument->getType(), $oldDocument->getRetentionYears());
        $this->archiveDocument(
            $oldDocument,
            'Document remplace par une nouvelle version',
            $retentionYears,
            false
        );

        $document = $this->createDocumentFromUpload(
            $newFile,
            $user,
            $oldDocument->getType(),
            $comment,
            $newVersion
        );

        $document
            ->setContract($oldDocument->getContract())
            ->setAbsence($oldDocument->getAbsence())
            ->setFormation($oldDocument->getFormation())
            ->setElementVariable($oldDocument->getElementVariable());

        $this->entityManager->persist($document);
        $this->entityManager->flush();

        $this->logger->info('Document replaced', [
            'document_id' => $document->getId(),
            'old_document_id' => $oldDocument->getId(),
            'user_id' => $user->getId(),
            'user_name' => $user->getFullName(),
            'type' => $document->getType(),
            'type_label' => $document->getTypeLabel(),
            'original_filename' => $document->getOriginalName(),
            'version' => $document->getVersion(),
            'old_version' => $oldDocument->getVersion(),
        ]);

        return $document;
    }

    public function archiveDocument(
        Document $document,
        string $reason,
        int $retentionYears,
        bool $flush = true
    ): void {
        if ($document->isArchived()) {
            return;
        }

        $sourcePath = $this->getDocumentPath($document, false);
        $targetPath = $this->getArchiveDirectory($document->getUser()) . '/' . $document->getFileName();

        try {
            $this->moveFile($sourcePath, $targetPath);
        } catch (\RuntimeException $exception) {
            $this->logger->warning('Unable to move file while archiving document', [
                'document_id' => $document->getId(),
                'user_id' => $document->getUser()->getId(),
                'path' => $sourcePath,
                'error' => $exception->getMessage(),
            ]);
        }

        $document->markAsArchived($reason, $retentionYears);

        if ($flush) {
            $this->entityManager->flush();
        }

        $this->logger->info('Document archived', [
            'document_id' => $document->getId(),
            'user_id' => $document->getUser()->getId(),
            'user_name' => $document->getUser()->getFullName(),
            'type' => $document->getType(),
            'type_label' => $document->getTypeLabel(),
            'reason' => $reason,
            'retention_years' => $retentionYears,
            'version' => $document->getVersion(),
            'archived_at' => $document->getArchivedAt()?->format('Y-m-d H:i:s'),
        ]);
    }

    public function restoreDocument(Document $document, bool $flush = true): void
    {
        if (!$document->isArchived()) {
            return;
        }

        $sourcePath = $this->getDocumentPath($document);
        $targetPath = $this->getUserDirectory($document->getUser()) . '/' . $document->getFileName();

        try {
            $this->moveFile($sourcePath, $targetPath);
        } catch (\RuntimeException $exception) {
            $this->logger->warning('Unable to move file while restoring document', [
                'document_id' => $document->getId(),
                'user_id' => $document->getUser()->getId(),
                'path' => $sourcePath,
                'error' => $exception->getMessage(),
            ]);
        }

        $document->restoreFromArchive();

        if ($flush) {
            $this->entityManager->flush();
        }

        $this->logger->info('Document restored', [
            'document_id' => $document->getId(),
            'user_id' => $document->getUser()->getId(),
            'user_name' => $document->getUser()->getFullName(),
            'type' => $document->getType(),
            'type_label' => $document->getTypeLabel(),
            'version' => $document->getVersion(),
        ]);
    }

    /**
     * @return Document[]
     */
    public function getArchivedDocuments(User $user): array
    {
        return $this->documentRepository->findArchivedByUser($user);
    }

    public function cleanExpiredDocuments(): int
    {
        $now = new \DateTimeImmutable();
        $expiredDocuments = $this->documentRepository->findExpiredArchives($now);

        $removed = 0;
        foreach ($expiredDocuments as $document) {
            $this->deleteDocument($document);
            $removed++;
        }

        if ($removed > 0) {
            $this->logger->info('Expired archived documents removed', [
                'count' => $removed,
            ]);
        }

        return $removed;
    }

    public function deleteDocument(Document $document): void
    {
        $filePath = $this->getDocumentPath($document);
        if (file_exists($filePath)) {
            unlink($filePath);
        }

        $this->entityManager->remove($document);
        $this->entityManager->flush();

        $this->logger->info('Document deleted', [
            'document_id' => $document->getId(),
            'user_id' => $document->getUser()->getId(),
            'user_name' => $document->getUser()->getFullName(),
            'type' => $document->getType(),
            'type_label' => $document->getTypeLabel(),
            'original_filename' => $document->getOriginalName(),
            'archived' => $document->isArchived(),
            'version' => $document->getVersion(),
        ]);
    }

    public function validateDocument(Document $document, ?string $comment = null): void
    {
        $document->markAsValidated($comment);
        $this->entityManager->flush();

        $this->logger->info('Document validated', [
            'document_id' => $document->getId(),
            'user_id' => $document->getUser()->getId(),
            'user_name' => $document->getUser()->getFullName(),
            'type' => $document->getType(),
            'type_label' => $document->getTypeLabel(),
            'comment' => $comment,
            'validated_at' => $document->getValidatedAt()?->format('Y-m-d H:i:s'),
            'validated_by' => $document->getValidatedBy()?->getFullName(),
        ]);
    }

    public function rejectDocument(Document $document, string $reason): void
    {
        $document->markAsRejected($reason);
        $this->entityManager->flush();

        $this->logger->info('Document rejected', [
            'document_id' => $document->getId(),
            'user_id' => $document->getUser()->getId(),
            'user_name' => $document->getUser()->getFullName(),
            'type' => $document->getType(),
            'type_label' => $document->getTypeLabel(),
            'reason' => $reason,
            'rejected_at' => $document->getValidatedAt()?->format('Y-m-d H:i:s'),
            'rejected_by' => $document->getValidatedBy()?->getFullName(),
        ]);
    }

    public function getCompletionStatus(User $user): array
    {
        $documents = $this->documentRepository->findActiveByUser($user);
        $documentsByType = [];

        foreach ($documents as $document) {
            $documentsByType[$document->getType()] = $document;
        }

        $contractType = null;
        if ($user->getActiveContract() instanceof Contract) {
            $contractType = $user->getActiveContract()->getType();
        }

        $categoriesConfig = $this->buildCompletionCategories($contractType);

        $categories = [];
        $missing = [];
        $totalRequired = 0;
        $completedRequired = 0;

        foreach ($categoriesConfig as $key => $config) {
            $requiredStatuses = [];
            foreach ($config['required'] as $type) {
                $status = $this->buildDocumentStatus($type, true, $documentsByType, $key);
                $requiredStatuses[] = $status;
                $totalRequired++;

                if ($status['uploaded']) {
                    $completedRequired++;
                } else {
                    $missing[] = [
                        'type' => $status['type'],
                        'label' => $status['label'],
                        'category' => $key,
                        'priority' => $status['priority'],
                    ];
                }
            }

            $optionalStatuses = [];
            foreach ($config['optional'] as $optionalConfig) {
                $type = is_array($optionalConfig) ? $optionalConfig['type'] : $optionalConfig;
                $priority = is_array($optionalConfig) && isset($optionalConfig['priority'])
                    ? $optionalConfig['priority']
                    : 'low';

                $status = $this->buildDocumentStatus($type, false, $documentsByType, $key, $priority);
                $optionalStatuses[] = $status;

                if (!$status['uploaded']) {
                    $missing[] = [
                        'type' => $status['type'],
                        'label' => $status['label'],
                        'category' => $key,
                        'priority' => $status['priority'],
                        'optional' => true,
                    ];
                }
            }

            $requiredTotal = count($requiredStatuses);
            $requiredCompleted = count(array_filter(
                $requiredStatuses,
                static fn(array $item): bool => $item['uploaded'] === true
            ));

            $categories[] = [
                'key' => $key,
                'label' => $config['label'],
                'required' => $requiredStatuses,
                'optional' => $optionalStatuses,
                'completion' => [
                    'required_total' => $requiredTotal,
                    'required_completed' => $requiredCompleted,
                    'percentage' => $requiredTotal > 0
                        ? round(($requiredCompleted / $requiredTotal) * 100, 1)
                        : 100.0,
                ],
            ];
        }

        $percentage = $totalRequired > 0
            ? round(($completedRequired / $totalRequired) * 100, 1)
            : 100.0;

        return [
            'total_required' => $totalRequired,
            'completed_required' => $completedRequired,
            'percentage' => $percentage,
            'is_complete' => $totalRequired > 0 ? $completedRequired === $totalRequired : true,
            'contract_type' => $contractType,
            'categories' => $categories,
            'missing' => $missing,
        ];
    }

    public function hasAllRequiredDocuments(User $user): bool
    {
        $status = $this->getCompletionStatus($user);

        return $status['total_required'] > 0 && $status['completed_required'] === $status['total_required'];
    }

    public function exportDocumentsList(User $user, string $format = 'pdf'): string
    {
        $status = $this->getCompletionStatus($user);

        if ($format !== 'pdf') {
            throw new \InvalidArgumentException(sprintf('Format "%s" non supporte.', $format));
        }

        $lines = $this->buildExportLines($user, $status);
        $pdfContent = $this->generateSimplePdf($lines);

        $directory = $this->getExportDirectory($user);
        $this->ensureDirectoryExists($directory);

        $fileName = sprintf(
            'documents-%d-%s.pdf',
            $user->getId(),
            (new \DateTimeImmutable())->format('Ymd-His')
        );

        $filePath = $directory . '/' . $fileName;
        file_put_contents($filePath, $pdfContent);

        $this->logger->info('Documents list exported', [
            'user_id' => $user->getId(),
            'path' => $filePath,
            'format' => $format,
        ]);

        return $filePath;
    }

    public function getDocumentPath(Document $document, bool $considerArchive = true): string
    {
        $directory = $this->getUserDirectory($document->getUser());

        if ($considerArchive && $document->isArchived()) {
            $directory = $this->getArchiveDirectory($document->getUser());
        }

        return $directory . '/' . $document->getFileName();
    }

    private function validateFile(UploadedFile $file): void
    {
        if (!$file->isValid()) {
            throw new \InvalidArgumentException('Le fichier n\'a pas ete uploade correctement.');
        }

        $errors = [];
        $fileSize = $file->getSize();

        if ($fileSize && $fileSize > self::MAX_FILE_SIZE) {
            $errors[] = sprintf(
                'Le fichier est trop volumineux (max %d Mo).',
                self::MAX_FILE_SIZE / 1024 / 1024
            );
        }

        $mimeType = $file->getClientMimeType();
        if ($mimeType && !in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            $errors[] = 'Type de fichier non autorise. Formats acceptes : PDF, JPG, PNG.';
        }

        if (!empty($errors)) {
            throw new \InvalidArgumentException(implode(' ', $errors));
        }
    }

    private function createDocumentFromUpload(
        UploadedFile $file,
        User $user,
        string $type,
        ?string $comment,
        int $version
    ): Document {
        $metadata = $this->captureMetadata($file);

        $targetDirectory = $this->getUserDirectory($user);
        $this->ensureDirectoryExists($targetDirectory);

        try {
            $file->move($targetDirectory, $metadata['fileName']);
        } catch (FileException $exception) {
            $this->logger->error('Failed to move uploaded document', [
                'error' => $exception->getMessage(),
                'user_id' => $user->getId(),
                'type' => $type,
            ]);

            throw new \RuntimeException('Erreur lors de l\'upload du fichier : ' . $exception->getMessage());
        }

        $document = new Document();
        $document
            ->setUser($user)
            ->setType($type)
            ->setFileName($metadata['fileName'])
            ->setOriginalName($metadata['originalName'])
            ->setMimeType($metadata['mimeType'])
            ->setFileSize($metadata['fileSize'])
            ->setComment($comment)
            ->setVersion($version);

        return $document;
    }

    private function captureMetadata(UploadedFile $file): array
    {
        $originalName = $file->getClientOriginalName();
        $extension = $file->guessExtension() ?: pathinfo($originalName, PATHINFO_EXTENSION);
        $extension = $extension ? '.' . $extension : '';
        $originalFilename = pathinfo($originalName, PATHINFO_FILENAME);

        $safeFilename = $this->slugger->slug($originalFilename ?: 'document');
        $uniqueFilename = $safeFilename . '-' . uniqid('', true) . $extension;

        return [
            'originalName' => $originalName,
            'fileName' => $uniqueFilename,
            'mimeType' => $file->getClientMimeType() ?? 'application/octet-stream',
            'fileSize' => $file->getSize() ?? 0,
        ];
    }

    private function getUserDirectory(User $user): string
    {
        return $this->uploadsDirectory . '/' . self::USER_FOLDER . '/' . $user->getId();
    }

    private function getArchiveDirectory(User $user): string
    {
        return $this->uploadsDirectory . '/' . self::ARCHIVE_FOLDER . '/' . $user->getId();
    }

    private function getExportDirectory(User $user): string
    {
        return $this->uploadsDirectory . '/' . self::EXPORT_FOLDER . '/' . $user->getId();
    }

    public function getSuggestedRetentionYears(string $type): int
    {
        return self::RETENTION_POLICIES[$type] ?? self::RETENTION_DEFAULT;
    }

    private function ensureDirectoryExists(string $directory): void
    {
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
    }

    private function resolveRetentionYears(string $type, ?int $override): int
    {
        if ($override !== null) {
            return max(0, $override);
        }

        return self::RETENTION_POLICIES[$type] ?? self::RETENTION_DEFAULT;
    }

    private function buildCompletionCategories(?string $contractType): array
    {
        $categories = [];

        foreach (self::COMPLETION_CATEGORIES as $key => $config) {
            $categories[$key] = [
                'label' => $config['label'],
                'required' => $config['required'],
                'optional' => $config['optional'],
            ];
        }

        if ($contractType && isset(self::CONTRACT_OPTIONAL_DOCUMENTS[$contractType])) {
            foreach (self::CONTRACT_OPTIONAL_DOCUMENTS[$contractType] as $categoryKey => $types) {
                foreach ($types as $type) {
                    $categories[$categoryKey]['optional'][] = [
                        'type' => $type,
                        'priority' => 'medium',
                    ];
                }
            }
        }

        return $categories;
    }

    private function buildDocumentStatus(
        string $type,
        bool $isRequired,
        array $documentsByType,
        string $categoryKey,
        ?string $priority = null
    ): array {
        $document = $documentsByType[$type] ?? null;
        $uploaded = $document instanceof Document;

        return [
            'type' => $type,
            'label' => Document::TYPES[$type] ?? $type,
            'uploaded' => $uploaded,
            'document' => $document,
            'priority' => $priority ?? ($isRequired ? 'high' : 'low'),
            'category' => $categoryKey,
        ];
    }

    private function buildExportLines(User $user, array $status): array
    {
        $lines = [];
        $now = new \DateTimeImmutable();

        $lines[] = sprintf('Documents RH - %s %s', $user->getFirstName(), $user->getLastName());
        $lines[] = sprintf('Genere le %s', $now->format('d/m/Y H:i'));
        $lines[] = sprintf(
            'Completion : %.1f%% (%d / %d)',
            $status['percentage'],
            $status['completed_required'],
            $status['total_required']
        );
        $lines[] = '';

        foreach ($status['categories'] as $category) {
            $lines[] = sprintf(
                '%s (%d / %d)',
                $category['label'],
                $category['completion']['required_completed'],
                $category['completion']['required_total']
            );

            foreach ($category['required'] as $required) {
                $lines[] = sprintf(
                    '  [%s] %s',
                    $required['uploaded'] ? 'X' : ' ',
                    $required['label']
                );
            }

            foreach ($category['optional'] as $optional) {
                $lines[] = sprintf(
                    '  (%s) %s',
                    $optional['uploaded'] ? 'X' : ' ',
                    $optional['label']
                );
            }

            $lines[] = '';
        }

        if (!empty($status['missing'])) {
            $lines[] = 'Documents a completer :';
            foreach ($status['missing'] as $missing) {
                $lines[] = sprintf(
                    '- %s (catégorie : %s, priorité : %s%s)',
                    $missing['label'],
                    $missing['category'],
                    $missing['priority'],
                    !empty($missing['optional']) ? ', optionnel' : ''
                );
            }
        }

        return $lines;
    }

    private function generateSimplePdf(array $lines): string
    {
        $stream = "BT\n/F1 12 Tf\n16 TL\n50 800 Td\n";

        foreach ($lines as $index => $line) {
            $escaped = $this->escapePdfText($line);
            if ($index > 0) {
                $stream .= "T*\n";
            }
            $stream .= sprintf('(%s) Tj' . "\n", $escaped);
        }

        $stream .= "ET";
        $length = strlen($stream);

        $objects = [
            [1, "<< /Type /Catalog /Pages 2 0 R >>"],
            [2, "<< /Type /Pages /Kids [3 0 R] /Count 1 >>"],
            [3, "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>"],
            [4, "<< /Length $length >>\nstream\n$stream\nendstream"],
            [5, "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>"],
        ];

        $buffer = "%PDF-1.4\n";
        $offsets = [0];

        foreach ($objects as [$number, $body]) {
            $offsets[$number] = strlen($buffer);
            $buffer .= $number . " 0 obj\n" . $body . "\nendobj\n";
        }

        $xrefPosition = strlen($buffer);
        $count = count($objects) + 1;

        $buffer .= "xref\n0 $count\n";
        $buffer .= "0000000000 65535 f \n";

        for ($i = 1; $i <= count($objects); $i++) {
            $offset = $offsets[$i] ?? 0;
            $buffer .= sprintf("%010d 00000 n \n", $offset);
        }

        $buffer .= "trailer << /Size $count /Root 1 0 R >>\n";
        $buffer .= "startxref\n$xrefPosition\n%%EOF";

        return $buffer;
    }

    private function escapePdfText(string $text): string
    {
        return str_replace(
            ['\\', '(', ')'],
            ['\\\\', '\\(', '\\)'],
            $text
        );
    }

    private function moveFile(string $source, string $destination): void
    {
        $directory = dirname($destination);
        $this->ensureDirectoryExists($directory);

        if (!file_exists($source)) {
            return;
        }

        if (@rename($source, $destination)) {
            return;
        }

        if (!@copy($source, $destination)) {
            throw new \RuntimeException(sprintf('Impossible de déplacer le fichier vers %s', $destination));
        }

        @unlink($source);
    }

    // ===== EMAIL NOTIFICATIONS =====

    /**
     * Send notification to admins when a document is uploaded by an employee
     */
    public function sendDocumentUploadedNotification(Document $document): void
    {
        try {
            // Get all users with ROLE_ADMIN or ROLE_RH
            $admins = $this->userRepository->findByRole('ROLE_ADMIN');

            if (empty($admins)) {
                $this->logger->warning('No admin users found to notify about document upload', [
                    'document_id' => $document->getId(),
                ]);
                return;
            }

            $validationUrl = $this->urlGenerator->generate(
                'admin_documents_view',
                ['id' => $document->getId()],
                UrlGeneratorInterface::ABSOLUTE_URL
            );

            foreach ($admins as $admin) {
                $email = (new TemplatedEmail())
                    ->from(new Address('noreply@rhnewlife.com', 'RH NewLife'))
                    ->to(new Address($admin->getEmail(), $admin->getFullName()))
                    ->replyTo(new Address('rh@rhnewlife.com', 'Service RH'))
                    ->subject('Nouveau document à valider')
                    ->htmlTemplate('emails/document_uploaded.html.twig')
                    ->context([
                        'document' => $document,
                        'user' => $document->getUser(),
                        'validationUrl' => $validationUrl,
                        'currentYear' => date('Y'),
                    ]);

                $this->mailer->send($email);
            }

            $this->logger->info('Document upload notification sent to admins', [
                'document_id' => $document->getId(),
                'admin_count' => count($admins),
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to send document upload notification', [
                'document_id' => $document->getId(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send notification to employee when their document is validated
     */
    public function sendDocumentValidatedNotification(Document $document, ?User $validator = null, ?string $comment = null): void
    {
        try {
            $user = $document->getUser();

            if (!$user || !$user->getEmail()) {
                $this->logger->warning('Cannot send validation notification: user or email missing', [
                    'document_id' => $document->getId(),
                ]);
                return;
            }

            $profileUrl = $this->urlGenerator->generate(
                'app_profile_documents',
                [],
                UrlGeneratorInterface::ABSOLUTE_URL
            );

            // Calculate completion percentage
            $completionData = $this->calculateUserCompletion($user);
            $completionPercent = $completionData['percentage'] ?? 0;

            $email = (new TemplatedEmail())
                ->from(new Address('noreply@rhnewlife.com', 'RH NewLife'))
                ->to(new Address($user->getEmail(), $user->getFullName()))
                ->replyTo(new Address('rh@rhnewlife.com', 'Service RH'))
                ->subject('✅ Votre document a été validé')
                ->htmlTemplate('emails/document_validated.html.twig')
                ->context([
                    'document' => $document,
                    'user' => $user,
                    'validator' => $validator,
                    'comment' => $comment,
                    'profileUrl' => $profileUrl,
                    'completionPercent' => $completionPercent,
                    'currentYear' => date('Y'),
                ]);

            $this->mailer->send($email);

            $this->logger->info('Document validation notification sent', [
                'document_id' => $document->getId(),
                'user_id' => $user->getId(),
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to send document validation notification', [
                'document_id' => $document->getId(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send notification to employee when their document is rejected
     */
    public function sendDocumentRejectedNotification(Document $document, string $reason): void
    {
        try {
            $user = $document->getUser();

            if (!$user || !$user->getEmail()) {
                $this->logger->warning('Cannot send rejection notification: user or email missing', [
                    'document_id' => $document->getId(),
                ]);
                return;
            }

            $resubmitUrl = $this->urlGenerator->generate(
                'app_profile_documents',
                [],
                UrlGeneratorInterface::ABSOLUTE_URL
            );

            $email = (new TemplatedEmail())
                ->from(new Address('noreply@rhnewlife.com', 'RH NewLife'))
                ->to(new Address($user->getEmail(), $user->getFullName()))
                ->replyTo(new Address('rh@rhnewlife.com', 'Service RH'))
                ->subject('⚠️ Document refusé - Action requise')
                ->htmlTemplate('emails/document_rejected.html.twig')
                ->context([
                    'document' => $document,
                    'user' => $user,
                    'reason' => $reason,
                    'resubmitUrl' => $resubmitUrl,
                    'currentYear' => date('Y'),
                ]);

            $this->mailer->send($email);

            $this->logger->info('Document rejection notification sent', [
                'document_id' => $document->getId(),
                'user_id' => $user->getId(),
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to send document rejection notification', [
                'document_id' => $document->getId(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send notification to admins about documents expiring soon
     * This method is intended to be called from a console command (cron job)
     */
    public function sendExpiringDocumentsNotification(array $expiringDocuments): void
    {
        try {
            if (empty($expiringDocuments)) {
                return;
            }

            $admins = $this->userRepository->findByRole('ROLE_ADMIN');

            if (empty($admins)) {
                $this->logger->warning('No admin users found to notify about expiring documents');
                return;
            }

            $archivesUrl = $this->urlGenerator->generate(
                'admin_documents_archives',
                [],
                UrlGeneratorInterface::ABSOLUTE_URL
            );

            // Enrich documents with calculated data
            $enrichedDocuments = array_map(function (Document $doc) {
                $expirationDate = $doc->getArchivedAt()
                    ? (clone $doc->getArchivedAt())->modify('+' . $doc->getRetentionYears() . ' years')
                    : null;

                $daysUntilExpiration = $expirationDate
                    ? (new \DateTime())->diff($expirationDate)->days
                    : 0;

                $viewUrl = $this->urlGenerator->generate(
                    'admin_documents_view',
                    ['id' => $doc->getId()],
                    UrlGeneratorInterface::ABSOLUTE_URL
                );

                return [
                    'document' => $doc,
                    'user' => $doc->getUser(),
                    'typeLabel' => $doc->getTypeLabel(),
                    'daysUntilExpiration' => $daysUntilExpiration,
                    'viewUrl' => $viewUrl,
                ];
            }, $expiringDocuments);

            foreach ($admins as $admin) {
                $email = (new TemplatedEmail())
                    ->from(new Address('noreply@rhnewlife.com', 'RH NewLife'))
                    ->to(new Address($admin->getEmail(), $admin->getFullName()))
                    ->replyTo(new Address('rh@rhnewlife.com', 'Service RH'))
                    ->subject('⏰ Documents proches de l\'expiration - ' . count($expiringDocuments) . ' document(s)')
                    ->htmlTemplate('emails/document_expiring_soon.html.twig')
                    ->context([
                        'expiringDocuments' => $enrichedDocuments,
                        'archivesUrl' => $archivesUrl,
                        'currentYear' => date('Y'),
                    ]);

                $this->mailer->send($email);
            }

            $this->logger->info('Expiring documents notification sent', [
                'document_count' => count($expiringDocuments),
                'admin_count' => count($admins),
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to send expiring documents notification', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Reconstruit l'historique d'un document depuis ses métadonnées
     * Cette méthode est temporaire en attendant l'implémentation de l'entité Journal (EP-11)
     *
     * @return array<int, array{action: string, icon: string, color: string, date: \DateTimeImmutable, user: ?User, details: ?string}>
     */
    public function getDocumentHistory(Document $document): array
    {
        $history = [];

        // 1. Événement : Upload initial
        if ($document->getUploadedAt()) {
            $history[] = [
                'action' => 'Document uploadé',
                'icon' => 'upload',
                'color' => 'blue',
                'date' => $document->getUploadedAt(),
                'user' => $document->getUser(),
                'details' => sprintf(
                    'Version %d • %s • %s',
                    $document->getVersion(),
                    $document->getOriginalName(),
                    $document->getFileSizeFormatted()
                ),
            ];
        }

        // 2. Événement : Remplacement (si version > 1)
        if ($document->getVersion() > 1) {
            // On suppose que le remplacement s'est fait peu avant l'upload actuel
            $history[] = [
                'action' => sprintf('Document remplacé (v%d → v%d)', $document->getVersion() - 1, $document->getVersion()),
                'icon' => 'refresh',
                'color' => 'gray',
                'date' => $document->getUploadedAt(),
                'user' => $document->getUser(),
                'details' => 'Ancienne version archivée automatiquement',
            ];
        }

        // 3. Événement : Validation
        if ($document->getStatus() === Document::STATUS_VALIDATED && $document->getValidatedAt()) {
            $history[] = [
                'action' => 'Document validé',
                'icon' => 'check',
                'color' => 'green',
                'date' => $document->getValidatedAt(),
                'user' => $document->getValidatedBy(),
                'details' => $document->getCommentaire(),
            ];
        }

        // 4. Événement : Rejet
        if ($document->getStatus() === Document::STATUS_REJECTED && $document->getValidatedAt()) {
            $history[] = [
                'action' => 'Document rejeté',
                'icon' => 'x',
                'color' => 'red',
                'date' => $document->getValidatedAt(),
                'user' => $document->getValidatedBy(),
                'details' => $document->getCommentaire() ?? 'Aucun motif précisé',
            ];
        }

        // 5. Événement : Archivage
        if ($document->isArchived() && $document->getArchivedAt()) {
            $history[] = [
                'action' => 'Document archivé',
                'icon' => 'archive',
                'color' => 'yellow',
                'date' => $document->getArchivedAt(),
                'user' => null, // L'archiveur n'est pas stocké actuellement
                'details' => sprintf(
                    'Rétention : %d ans • Motif : %s',
                    $document->getRetentionYears() ?? 5,
                    $document->getArchiveReason() ?? 'Non précisé'
                ),
            ];
        }

        // Trier par date décroissante (plus récent en premier)
        usort($history, function ($a, $b) {
            return $b['date'] <=> $a['date'];
        });

        return $history;
    }
}
