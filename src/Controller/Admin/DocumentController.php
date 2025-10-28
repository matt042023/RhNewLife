<?php

namespace App\Controller\Admin;

use App\Entity\Document;
use App\Repository\DocumentRepository;
use App\Repository\UserRepository;
use App\Security\Voter\DocumentVoter;
use App\Service\DocumentManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

#[Route('/admin/documents', name: 'app_admin_documents_')]
class DocumentController extends AbstractController
{
    public function __construct(
        private readonly DocumentRepository $documentRepository,
        private readonly UserRepository $userRepository,
        private readonly DocumentManager $documentManager
    ) {
    }

    #[Route('/', name: 'list', methods: ['GET'])]
    public function list(Request $request): Response
    {
        $this->assertAdminOrDirector();

        $filters = $this->extractFilters($request);
        $documents = $this->documentRepository->searchForAdmin($filters);

        $stats = [
            'total' => count($documents),
            'pending' => $this->countByStatus($documents, Document::STATUS_PENDING),
            'validated' => $this->countByStatus($documents, Document::STATUS_VALIDATED),
            'rejected' => $this->countByStatus($documents, Document::STATUS_REJECTED),
        ];

        return $this->render('admin/documents/list.html.twig', [
            'documents' => $documents,
            'filters' => $filters,
            'stats' => $stats,
            'types' => Document::TYPES,
            'statuses' => [
                Document::STATUS_PENDING => 'En attente',
                Document::STATUS_VALIDATED => 'Validé',
                Document::STATUS_REJECTED => 'Rejeté',
            ],
            'isArchiveView' => false,
        ]);
    }

    #[Route('/archives', name: 'archives', methods: ['GET'])]
    public function archives(Request $request): Response
    {
        $this->assertAdminOrDirector();

        $filters = $this->extractFilters($request);
        $filters['archived'] = true;

        $documents = $this->documentRepository->searchForAdmin($filters);

        $stats = [
            'total' => count($documents),
            'pending' => $this->countByStatus($documents, Document::STATUS_PENDING),
            'validated' => $this->countByStatus($documents, Document::STATUS_VALIDATED),
            'rejected' => $this->countByStatus($documents, Document::STATUS_REJECTED),
        ];

        return $this->render('admin/documents/list.html.twig', [
            'documents' => $documents,
            'filters' => $filters,
            'stats' => $stats,
            'types' => Document::TYPES,
            'statuses' => [
                Document::STATUS_PENDING => 'En attente',
                Document::STATUS_VALIDATED => 'Validé',
                Document::STATUS_REJECTED => 'Rejeté',
            ],
            'isArchiveView' => true,
        ]);
    }

    #[Route('/{id}', name: 'view', methods: ['GET'])]
    public function view(Document $document): Response
    {
        if ($document->isArchived()) {
            $this->denyAccessUnlessGranted(DocumentVoter::VIEW_ARCHIVED, $document);
        } else {
            $this->denyAccessUnlessGranted(DocumentVoter::VIEW, $document);
        }

        return $this->render('admin/documents/view.html.twig', [
            'document' => $document,
            'canValidate' => $this->isGranted(DocumentVoter::VALIDATE, $document),
            'canArchive' => $this->isGranted(DocumentVoter::ARCHIVE, $document),
            'canRestore' => $this->isGranted(DocumentVoter::RESTORE, $document),
            'canReplace' => $this->isGranted(DocumentVoter::REPLACE, $document),
            'downloadRoute' => $this->generateUrl('app_documents_download', ['id' => $document->getId()]),
        ]);
    }

    #[Route('/upload', name: 'upload', methods: ['POST'])]
    public function upload(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $userId = $request->request->getInt('user_id');
        $type = (string) $request->request->get('type');
        $comment = $request->request->get('comment');

        /** @var UploadedFile|null $file */
        $file = $request->files->get('file');

        if (!$userId || !$file) {
            return $this->json([
                'success' => false,
                'message' => 'Utilisateur et fichier obligatoires.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $user = $this->userRepository->find($userId);

        if (!$user) {
            return $this->json([
                'success' => false,
                'message' => 'Utilisateur introuvable.',
            ], Response::HTTP_NOT_FOUND);
        }

        $this->denyAccessUnlessGranted(DocumentVoter::UPLOAD, $user);

        try {
            $document = $this->documentManager->uploadDocument($file, $user, $type, $comment);
        } catch (\InvalidArgumentException $exception) {
            return $this->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        } catch (\Throwable $exception) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de l\'upload du document.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->json([
            'success' => true,
            'document' => [
                'id' => $document->getId(),
                'type' => $document->getType(),
                'typeLabel' => $document->getTypeLabel(),
                'status' => $document->getStatus(),
                'uploadedAt' => $document->getUploadedAt()?->format('Y-m-d H:i:s'),
            ],
        ], Response::HTTP_CREATED);
    }

    #[Route('/{id}/validate', name: 'validate', methods: ['POST'])]
    public function validate(Document $document, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(DocumentVoter::VALIDATE, $document);

        $comment = $request->request->get('comment');
        $this->documentManager->validateDocument($document, $comment);

        return $this->json(['success' => true]);
    }

    #[Route('/{id}/reject', name: 'reject', methods: ['POST'])]
    public function reject(Document $document, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(DocumentVoter::VALIDATE, $document);

        $reason = trim((string) $request->request->get('reason'));

        if ($reason === '') {
            return $this->json([
                'success' => false,
                'message' => 'Merci de préciser un motif de rejet.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $this->documentManager->rejectDocument($document, $reason);

        return $this->json(['success' => true]);
    }

    #[Route('/{id}/archive', name: 'archive', methods: ['POST'])]
    public function archive(Document $document, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(DocumentVoter::ARCHIVE, $document);

        $reason = trim((string) $request->request->get('reason', ''));
        $retentionYears = $request->request->getInt('retention_years', 0);

        if ($reason === '') {
            return $this->json([
                'success' => false,
                'message' => 'Merci de préciser un motif d\'archivage.',
            ], Response::HTTP_BAD_REQUEST);
        }

        if ($retentionYears <= 0) {
            $retentionYears = $this->documentManager->getSuggestedRetentionYears($document->getType());
        }

        $this->documentManager->archiveDocument($document, $reason, $retentionYears);

        return $this->json(['success' => true]);
    }

    #[Route('/{id}/restore', name: 'restore', methods: ['POST'])]
    public function restore(Document $document): JsonResponse
    {
        $this->denyAccessUnlessGranted(DocumentVoter::RESTORE, $document);

        $this->documentManager->restoreDocument($document);

        return $this->json(['success' => true]);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(Document $document): JsonResponse
    {
        $this->denyAccessUnlessGranted(DocumentVoter::DELETE, $document);

        try {
            $this->documentManager->deleteDocument($document);
        } catch (\Throwable $exception) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression du document.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->json(['success' => true]);
    }

    #[Route('/{id}/download', name: 'download', methods: ['GET'])]
    public function download(Document $document): Response
    {
        if ($document->isArchived()) {
            $this->denyAccessUnlessGranted(DocumentVoter::VIEW_ARCHIVED, $document);
        } else {
            $this->denyAccessUnlessGranted(DocumentVoter::DOWNLOAD, $document);
        }

        $filePath = $this->documentManager->getDocumentPath($document);

        if (!file_exists($filePath)) {
            throw $this->createNotFoundException('Le fichier demandé est introuvable.');
        }

        return new BinaryFileResponse($filePath);
    }

    #[Route('/export/user/{userId}', name: 'export_user', methods: ['GET'])]
    public function exportUserDocuments(int $userId): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $user = $this->userRepository->find($userId);

        if (!$user) {
            throw $this->createNotFoundException('Utilisateur introuvable.');
        }

        $filePath = $this->documentManager->exportDocumentsList($user);

        return $this->file($filePath, basename($filePath), ResponseHeaderBag::DISPOSITION_ATTACHMENT);
    }

    private function assertAdminOrDirector(): void
    {
        if (!$this->isGranted('ROLE_ADMIN') && !$this->isGranted('ROLE_DIRECTOR')) {
            throw $this->createAccessDeniedException('Accès réservé aux administrateurs et directeurs.');
        }
    }

    /**
     * @return array{
     *     status: string|null,
     *     type: string|null,
     *     archived: bool|null,
     *     user: int|null,
     *     search: string|null,
     *     from: \DateTimeImmutable|null,
     *     to: \DateTimeImmutable|null,
     *     limit: int|null,
     *     min_size: int|null,
     *     max_size: int|null
     * }
     */
    private function extractFilters(Request $request): array
    {
        $archived = $request->query->get('archived');
        $from = $this->parseDate($request->query->get('from'));
        $to = $this->parseDate($request->query->get('to'));

        return [
            'status' => $request->query->get('status') ?: null,
            'type' => $request->query->get('type') ?: null,
            'archived' => $archived === null ? null : filter_var($archived, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE),
            'user' => $request->query->getInt('user_id') ?: null,
            'search' => $request->query->get('search') ?: null,
            'from' => $from,
            'to' => $to,
            'limit' => $request->query->getInt('limit') ?: 100,
            'min_size' => $request->query->getInt('min_size') ?: null,
            'max_size' => $request->query->getInt('max_size') ?: null,
        ];
    }

    private function parseDate(?string $value): ?\DateTimeImmutable
    {
        if (!$value) {
            return null;
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * @param Document[] $documents
     */
    private function countByStatus(array $documents, string $status): int
    {
        return count(array_filter($documents, static function (Document $document) use ($status): bool {
            return $document->getStatus() === $status;
        }));
    }
}
