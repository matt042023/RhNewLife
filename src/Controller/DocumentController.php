<?php

namespace App\Controller;

use App\Entity\Document;
use App\Entity\User;
use App\Repository\DocumentRepository;
use App\Service\DocumentManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/documents')]
class DocumentController extends AbstractController
{
    public function __construct(
        private DocumentManager $documentManager,
        private DocumentRepository $documentRepository
    ) {}

    /**
     * Liste des documents de l'utilisateur
     */
    #[Route('/', name: 'app_documents_list', methods: ['GET'])]
    public function list(#[CurrentUser] User $user): Response
    {
        $documents = $this->documentRepository->findByUser($user);
        $completionStatus = $this->documentManager->getCompletionStatus($user);

        return $this->render('documents/list.html.twig', [
            'documents' => $documents,
            'completionStatus' => $completionStatus,
        ]);
    }

    /**
     * Upload d'un document (API JSON)
     */
    #[Route('/upload', name: 'app_documents_upload', methods: ['POST'])]
    public function upload(Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $this->denyAccessUnlessGranted('DOCUMENT_UPLOAD', $user);

        /** @var UploadedFile|null $file */
        $file = $request->files->get('file');
        $type = $request->request->get('type');
        $comment = $request->request->get('comment');

        if (!$file) {
            return $this->json(['success' => false, 'message' => 'Aucun fichier fourni.'], Response::HTTP_BAD_REQUEST);
        }

        // Debug info
        if (!$file->isValid()) {
            $error = $file->getError();
            $errorMessage = match ($error) {
                UPLOAD_ERR_INI_SIZE => 'Le fichier dépasse la limite upload_max_filesize du php.ini',
                UPLOAD_ERR_FORM_SIZE => 'Le fichier dépasse la limite MAX_FILE_SIZE du formulaire',
                UPLOAD_ERR_PARTIAL => 'Le fichier n\'a été que partiellement uploadé',
                UPLOAD_ERR_NO_FILE => 'Aucun fichier n\'a été uploadé',
                UPLOAD_ERR_NO_TMP_DIR => 'Dossier temporaire manquant',
                UPLOAD_ERR_CANT_WRITE => 'Échec de l\'écriture du fichier sur le disque',
                UPLOAD_ERR_EXTENSION => 'Une extension PHP a arrêté l\'upload du fichier',
                default => 'Erreur inconnue lors de l\'upload: ' . $error,
            };
            return $this->json(['success' => false, 'message' => $errorMessage], Response::HTTP_BAD_REQUEST);
        }

        // Liste des types valides (constantes)
        $validTypes = [
            Document::TYPE_CNI,
            Document::TYPE_RIB,
            Document::TYPE_DOMICILE,
            Document::TYPE_HONORABILITE,
            Document::TYPE_DIPLOME,
            Document::TYPE_CONTRAT,
            Document::TYPE_OTHER,
        ];

        if (!$type || !in_array($type, $validTypes)) {
            return $this->json(['success' => false, 'message' => 'Type de document invalide: ' . $type], Response::HTTP_BAD_REQUEST);
        }

        try {
            $document = $this->documentManager->uploadDocument($file, $user, $type, $comment);

            return $this->json([
                'success' => true,
                'message' => 'Document uploadé avec succès.',
                'document' => [
                    'id' => $document->getId(),
                    'type' => $document->getType(),
                    'typeLabel' => $document->getTypeLabel(),
                    'originalName' => $document->getOriginalName(),
                    'fileSize' => $document->getFileSizeFormatted(),
                    'uploadedAt' => $document->getUploadedAt()->format('d/m/Y H:i'),
                    'status' => $document->getStatus(),
                ],
            ], Response::HTTP_CREATED);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['success' => false, 'message' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'message' => 'Erreur lors de l\'upload : ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Suppression d'un document
     */
    #[Route('/{id}', name: 'app_documents_delete', methods: ['DELETE'])]
    public function delete(Document $document): JsonResponse
    {
        $this->denyAccessUnlessGranted('DOCUMENT_DELETE', $document);

        try {
            $this->documentManager->deleteDocument($document);

            return $this->json([
                'success' => true,
                'message' => 'Document supprimé avec succès.',
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Erreur lors de la suppression.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Téléchargement d'un document
     */
    #[Route('/{id}/download', name: 'app_documents_download', methods: ['GET'])]
    public function download(Document $document): Response
    {
        $this->denyAccessUnlessGranted('DOCUMENT_DOWNLOAD', $document);

        $filePath = $this->documentManager->getDocumentPath($document);

        if (!file_exists($filePath)) {
            throw $this->createNotFoundException('Le fichier n\'existe pas.');
        }

        return new BinaryFileResponse($filePath);
    }

    /**
     * Statut de complétion des documents (API)
     */
    #[Route('/completion-status', name: 'app_documents_completion_status', methods: ['GET'])]
    public function completionStatus(#[CurrentUser] User $user): JsonResponse
    {
        $status = $this->documentManager->getCompletionStatus($user);

        return $this->json($status);
    }
}
