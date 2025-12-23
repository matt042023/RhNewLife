<?php

namespace App\Controller;

use App\Entity\Document;
use App\Entity\User;
use App\Repository\DocumentRepository;
use App\Security\Voter\DocumentVoter;
use App\Service\ContractManager;
use App\Service\DocumentManager;
use App\Service\ProfileUpdateRequestManager;
use App\Service\UserManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/profile')]
#[IsGranted('ROLE_USER')]
class ProfileController extends AbstractController
{
    public function __construct(
        private UserManager $userManager,
        private ProfileUpdateRequestManager $profileUpdateRequestManager,
        private DocumentManager $documentManager,
        private DocumentRepository $documentRepository,
        private ContractManager $contractManager
    ) {}

    #[Route('', name: 'app_profile_view', methods: ['GET'])]
    public function view(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $completionStatus = $this->userManager->getUserCompletionStatus($user);
        $activeContract = $user->getActiveContract();

        return $this->render('profile/view.html.twig', [
            'user' => $user,
            'completionStatus' => $completionStatus,
            'activeContract' => $activeContract,
        ]);
    }

    #[Route('/edit-request', name: 'app_profile_edit_request', methods: ['GET', 'POST'])]
    public function editRequest(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if ($request->isMethod('POST')) {
            $errors = [];

            try {
                $requestedData = [];

                $phone = $request->request->get('phone');
                if ($phone !== null && $phone !== $user->getPhone()) {
                    $requestedData['phone'] = $phone;
                }

                $address = $request->request->get('address');
                if ($address !== null && $address !== $user->getAddress()) {
                    $requestedData['address'] = $address;
                }

                $familyStatus = $request->request->get('familyStatus');
                if ($familyStatus !== null && $familyStatus !== $user->getFamilyStatus()) {
                    $requestedData['familyStatus'] = $familyStatus;
                }

                $children = $request->request->get('children');
                if ($children !== null && (int) $children !== $user->getChildren()) {
                    $requestedData['children'] = (int) $children;
                }

                $iban = $request->request->get('iban');
                if ($iban !== null && $iban !== $user->getIban()) {
                    $requestedData['iban'] = $iban;
                }

                $bic = $request->request->get('bic');
                if ($bic !== null && $bic !== $user->getBic()) {
                    $requestedData['bic'] = $bic;
                }

                $mutuelleEnabled = $request->request->get('mutuelleEnabled') === '1';
                if ($mutuelleEnabled !== $user->getHealth()->isMutuelleEnabled()) {
                    $requestedData['mutuelleEnabled'] = $mutuelleEnabled;
                }

                $prevoyanceEnabled = $request->request->get('prevoyanceEnabled') === '1';
                if ($prevoyanceEnabled !== $user->getHealth()->isPrevoyanceEnabled()) {
                    $requestedData['prevoyanceEnabled'] = $prevoyanceEnabled;
                }

                $reason = $request->request->get('reason');

                if (empty($requestedData)) {
                    $errors[] = 'Aucune modification détectée.';
                } else {
                    $this->profileUpdateRequestManager->createRequest(
                        $user,
                        $requestedData,
                        $reason
                    );

                    $this->addFlash('success', 'Votre demande de modification a été envoyée à l\'administrateur.');
                    return $this->redirectToRoute('app_profile_view');
                }
            } catch (\Exception $exception) {
                $errors[] = 'Erreur : ' . $exception->getMessage();
            }

            return $this->render('profile/edit_request.html.twig', [
                'user' => $user,
                'errors' => $errors,
            ]);
        }

        return $this->render('profile/edit_request.html.twig', [
            'user' => $user,
            'errors' => [],
        ]);
    }

    #[Route('/documents', name: 'app_profile_documents', methods: ['GET'])]
    public function documents(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        // Auto-créer les documents pour le contrat actif s'ils n'existent pas
        $activeContract = $user->getActiveContract();
        if ($activeContract) {
            $this->contractManager->createDocumentsForContract($activeContract, $user);
        }

        $documents = $this->documentRepository->findActiveByUser($user);
        $completion = $this->documentManager->getCompletionStatus($user);

        $documentsByType = [];
        foreach ($documents as $document) {
            $documentsByType[$document->getType()][] = $document;
        }

        return $this->render('profile/documents.html.twig', [
            'user' => $user,
            'documents' => $documents,
            'documentsByType' => $documentsByType,
            'completion' => $completion,
            'categories' => $completion['categories'] ?? [],
            'missingDocuments' => $completion['missing'] ?? [],
            'types' => Document::TYPES,
        ]);
    }

    #[Route('/documents/upload', name: 'app_profile_documents_upload', methods: ['POST'])]
    public function uploadDocument(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        // Sécurité: Un salarié ne peut uploader que ses propres documents
        $this->denyAccessUnlessGranted(DocumentVoter::UPLOAD, $user);

        /** @var UploadedFile|null $file */
        $file = $request->files->get('file');
        $type = (string) $request->request->get('type');
        $comment = $request->request->get('comment');

        if (!$file instanceof UploadedFile) {
            return $this->json([
                'success' => false,
                'message' => 'Aucun fichier fourni.',
            ], Response::HTTP_BAD_REQUEST);
        }

        if (!isset(Document::TYPES[$type])) {
            return $this->json([
                'success' => false,
                'message' => 'Type de document invalide.',
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            // Le salarié upload pour lui-même, donc user = uploadedBy
            $document = $this->documentManager->uploadDocument($file, $user, $type, $comment, $user);
        } catch (\InvalidArgumentException $exception) {
            return $this->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        } catch (\Throwable $exception) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors du téléversement du document.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $completion = $this->documentManager->getCompletionStatus($user);

        return $this->json([
            'success' => true,
            'message' => 'Document téléversé avec succès',
            'document' => $this->serializeDocument($document),
            'completion' => $this->serializeCompletionStatus($completion),
        ], Response::HTTP_CREATED);
    }

    #[Route('/documents/{id}/replace', name: 'app_profile_documents_replace', methods: ['POST'])]
    public function replaceDocument(Document $document, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        // Check permissions with detailed error message
        if (!$this->isGranted(DocumentVoter::REPLACE, $document)) {
            return $this->json([
                'success' => false,
                'message' => sprintf(
                    'Vous n\'êtes pas autorisé à remplacer ce document. Type: %s, Statut: %s, Archivé: %s, Propriétaire: %s',
                    $document->getType(),
                    $document->getStatus(),
                    $document->isArchived() ? 'Oui' : 'Non',
                    $document->getUser()->getId() === $user->getId() ? 'Oui' : 'Non'
                ),
            ], Response::HTTP_FORBIDDEN);
        }

        /** @var UploadedFile|null $file */
        $file = $request->files->get('file');
        $comment = $request->request->get('comment');

        if (!$file instanceof UploadedFile) {
            return $this->json([
                'success' => false,
                'message' => 'Aucun fichier fourni.',
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            // Le salarié remplace son propre document, donc uploadedBy = user
            $newDocument = $this->documentManager->replaceDocument($document, $file, $comment, $user);
        } catch (\InvalidArgumentException $exception) {
            return $this->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        } catch (\Throwable $exception) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors du remplacement du document.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $completion = $this->documentManager->getCompletionStatus($user);

        return $this->json([
            'success' => true,
            'message' => 'Document remplacé avec succès',
            'document' => $this->serializeDocument($newDocument),
            'completion' => $this->serializeCompletionStatus($completion),
        ]);
    }

    #[Route('/documents/{id}', name: 'app_profile_documents_delete', methods: ['DELETE'])]
    public function deleteDocument(Document $document): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $this->denyAccessUnlessGranted(DocumentVoter::DELETE, $document);

        try {
            $this->documentManager->deleteDocument($document);
        } catch (\Throwable $exception) {
            return $this->json([
                'success' => false,
                'message' => 'Impossible de supprimer ce document.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $completion = $this->documentManager->getCompletionStatus($user);

        return $this->json([
            'success' => true,
            'message' => 'Document supprimé avec succès',
            'completion' => $this->serializeCompletionStatus($completion),
        ]);
    }

    #[Route('/documents/{id}', name: 'app_profile_documents_detail', methods: ['GET'])]
    public function documentDetail(Document $document): Response
    {
        $this->denyAccessUnlessGranted(DocumentVoter::VIEW, $document);

        $history = $this->documentManager->getDocumentHistory($document);

        return $this->render('profile/document_detail.html.twig', [
            'document' => $document,
            'history' => $history,
            'canReplace' => $this->isGranted(DocumentVoter::REPLACE, $document),
            'canDelete' => $this->isGranted(DocumentVoter::DELETE, $document),
            'downloadRoute' => $this->generateUrl('app_profile_documents_download', ['id' => $document->getId()]),
        ]);
    }

    #[Route('/documents/{id}/view', name: 'app_profile_documents_view', methods: ['GET'])]
    public function viewDocument(Document $document): Response
    {
        $this->denyAccessUnlessGranted(DocumentVoter::VIEW, $document);

        $filePath = $this->documentManager->getDocumentPath($document);
        if (!is_file($filePath)) {
            throw $this->createNotFoundException('Le fichier demandé est introuvable.');
        }

        // Return HTML fragment for modal content
        return $this->render('profile/partials/_document_viewer.html.twig', [
            'document' => $document,
            'filePath' => $filePath,
        ]);
    }

    #[Route('/documents/{id}/download', name: 'app_profile_documents_download', methods: ['GET'])]
    public function downloadDocument(Document $document): Response
    {
        $this->denyAccessUnlessGranted(DocumentVoter::DOWNLOAD, $document);

        $filePath = $this->documentManager->getDocumentPath($document);
        if (!is_file($filePath)) {
            throw $this->createNotFoundException('Le fichier demandé est introuvable.');
        }

        $response = new BinaryFileResponse($filePath);

        // Force le téléchargement au lieu de l'affichage dans le navigateur
        $response->setContentDisposition(
            'attachment',
            $document->getOriginalName()
        );

        return $response;
    }

    #[Route('/documents/{id}/preview', name: 'app_profile_documents_preview', methods: ['GET'])]
    public function previewDocument(Document $document): Response
    {
        $this->denyAccessUnlessGranted(DocumentVoter::VIEW, $document);

        $filePath = $this->documentManager->getDocumentPath($document);
        if (!is_file($filePath)) {
            throw $this->createNotFoundException('Le fichier demandé est introuvable.');
        }

        $response = new BinaryFileResponse($filePath);

        // Affiche le fichier dans le navigateur (inline)
        $response->setContentDisposition(
            'inline',
            $document->getOriginalName()
        );

        return $response;
    }

    #[Route('/documents/completion', name: 'app_profile_documents_completion', methods: ['GET'])]
    public function completion(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->json($this->documentManager->getCompletionStatus($user));
    }

    #[Route('/contract', name: 'app_profile_contract', methods: ['GET'])]
    public function contract(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $activeContract = $user->getActiveContract();
        $allContracts = $user->getContracts();

        if (!$activeContract && count($allContracts) === 0) {
            $this->addFlash('info', 'Vous n\'avez pas encore de contrat enregistré.');
        }

        return $this->render('profile/contract.html.twig', [
            'user' => $user,
            'activeContract' => $activeContract,
            'allContracts' => $allContracts,
        ]);
    }

    private function serializeDocument(Document $document): array
    {
        return [
            'id' => $document->getId(),
            'type' => $document->getType(),
            'typeLabel' => Document::TYPES[$document->getType()] ?? $document->getType(),
            'status' => $document->getStatus(),
            'uploadedAt' => $document->getUploadedAt()?->format('Y-m-d H:i:s'),
            'fileSize' => $document->getFileSize(),
            'fileSizeFormatted' => $document->getFileSizeFormatted(),
            'archived' => $document->isArchived(),
        ];
    }

    private function serializeCompletionStatus(array $completion): array
    {
        // Serialize categories to avoid circular references
        $serializedCategories = [];

        foreach ($completion['categories'] as $category) {
            $serializedRequired = [];
            foreach ($category['required'] as $item) {
                $serializedRequired[] = [
                    'type' => $item['type'],
                    'label' => $item['label'],
                    'uploaded' => $item['uploaded'],
                    'priority' => $item['priority'],
                    'category' => $item['category'],
                    'document' => $item['document'] ? $this->serializeDocument($item['document']) : null,
                ];
            }

            $serializedOptional = [];
            foreach ($category['optional'] as $item) {
                $serializedOptional[] = [
                    'type' => $item['type'],
                    'label' => $item['label'],
                    'uploaded' => $item['uploaded'],
                    'priority' => $item['priority'],
                    'category' => $item['category'],
                    'document' => $item['document'] ? $this->serializeDocument($item['document']) : null,
                ];
            }

            $serializedCategories[] = [
                'key' => $category['key'],
                'label' => $category['label'],
                'required' => $serializedRequired,
                'optional' => $serializedOptional,
                'completion' => $category['completion'],
            ];
        }

        return [
            'total_required' => $completion['total_required'],
            'completed_required' => $completion['completed_required'],
            'percentage' => $completion['percentage'],
            'is_complete' => $completion['is_complete'],
            'contract_type' => $completion['contract_type'],
            'categories' => $serializedCategories,
            'missing' => $completion['missing'],
        ];
    }
}
