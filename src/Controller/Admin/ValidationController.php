<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Repository\DocumentRepository;
use App\Service\OnboardingManager;
use App\Service\DocumentManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/validation')]
#[IsGranted('ROLE_ADMIN')]
class ValidationController extends AbstractController
{
    public function __construct(
        private UserRepository $userRepository,
        private DocumentRepository $documentRepository,
        private OnboardingManager $onboardingManager,
        private DocumentManager $documentManager
    ) {
    }

    /**
     * Liste des dossiers en attente de validation - Redirige vers la nouvelle page unifiée
     */
    #[Route('', name: 'app_admin_validation_list', methods: ['GET'])]
    public function list(): Response
    {
        // Redirection vers la nouvelle page unifiée avec le filtre "à valider"
        return $this->redirectToRoute('app_admin_onboarding_list', [
            'filter' => 'a_valider',
        ]);
    }

    /**
     * Validation d'un dossier complet
     */
    #[Route('/{id}', name: 'app_admin_validation_review', methods: ['GET', 'POST'])]
    public function review(User $user, Request $request): Response
    {
        $this->denyAccessUnlessGranted('USER_VALIDATE', $user);

        if ($user->getStatus() !== User::STATUS_ONBOARDING) {
            $this->addFlash('warning', 'Ce dossier n\'est pas en attente de validation.');
            return $this->redirectToRoute('app_admin_validation_list');
        }

        // Récupère les documents et le statut de complétion
        $documents = $this->documentRepository->findByUser($user);
        $docCompletionStatus = $this->documentManager->getCompletionStatus($user);

        // Prépare le statut de complétion pour le template
        $completionStatus = [
            'uploaded' => $docCompletionStatus['completed_required'],
            'required' => $docCompletionStatus['total_required'],
            'percentage' => round($docCompletionStatus['percentage']),
            'hasPhone' => !empty($user->getPhone()),
            'hasAddress' => !empty($user->getAddress()),
            'hasIban' => !empty($user->getIban()),
            'hasBic' => !empty($user->getBic()),
        ];


        return $this->render('admin/validation/review.html.twig', [
            'user' => $user,
            'documents' => $documents,
            'completionStatus' => $completionStatus,
        ]);
    }

    /**
     * Valider un dossier complet (onboarding)
     */
    #[Route('/{id}/validate', name: 'app_admin_validation_validate', methods: ['POST'])]
    public function validate(User $user): Response
    {
        $this->denyAccessUnlessGranted('USER_VALIDATE', $user);

        if ($user->getStatus() !== User::STATUS_ONBOARDING) {
            $this->addFlash('warning', 'Ce dossier n\'est pas en attente de validation.');
            return $this->redirectToRoute('app_admin_validation_list');
        }

        try {
            $this->onboardingManager->validateOnboarding($user);
            $this->addFlash('success', 'Dossier validé ! L\'utilisateur a été activé.');
            return $this->redirectToRoute('app_admin_onboarding_list');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur : ' . $e->getMessage());
            return $this->redirectToRoute('app_admin_validation_review', ['id' => $user->getId()]);
        }
    }

    /**
     * Rejeter un dossier complet
     */
    #[Route('/{id}/reject', name: 'app_admin_validation_reject', methods: ['POST'])]
    public function reject(User $user, Request $request): Response
    {
        $this->denyAccessUnlessGranted('USER_VALIDATE', $user);

        $reason = $request->request->get('reason', 'Dossier incomplet ou non conforme');

        // TODO: Implémenter la logique de rejet (email, changement de statut, etc.)
        $this->addFlash('warning', 'Fonctionnalité de rejet en cours de développement.');

        return $this->redirectToRoute('app_admin_validation_review', ['id' => $user->getId()]);
    }

    /**
     * Valider un document spécifique
     */
    #[Route('/document/{id}/validate', name: 'app_admin_validation_document_validate', methods: ['POST'])]
    public function validateDocument(int $id, Request $request): Response
    {
        $document = $this->documentRepository->find($id);

        if (!$document) {
            throw $this->createNotFoundException('Document non trouvé.');
        }

        $this->denyAccessUnlessGranted('DOCUMENT_VALIDATE', $document);

        $comment = $request->request->get('comment');

        try {
            $this->documentManager->validateDocument($document, $comment);
            $this->addFlash('success', 'Document validé.');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur : ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_admin_validation_review', [
            'id' => $document->getUser()->getId(),
        ]);
    }

    /**
     * Rejeter un document
     */
    #[Route('/document/{id}/reject', name: 'app_admin_validation_document_reject', methods: ['POST'])]
    public function rejectDocument(int $id, Request $request): Response
    {
        $document = $this->documentRepository->find($id);

        if (!$document) {
            throw $this->createNotFoundException('Document non trouvé.');
        }

        $this->denyAccessUnlessGranted('DOCUMENT_VALIDATE', $document);

        $reason = $request->request->get('reason');

        if (!$reason) {
            $this->addFlash('error', 'Une raison de rejet est requise.');
            return $this->redirectToRoute('app_admin_validation_review', [
                'id' => $document->getUser()->getId(),
            ]);
        }

        try {
            $this->documentManager->rejectDocument($document, $reason);
            $this->addFlash('success', 'Document rejeté.');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur : ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_admin_validation_review', [
            'id' => $document->getUser()->getId(),
        ]);
    }
}
