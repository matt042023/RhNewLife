<?php

namespace App\Controller\Admin;

use App\Entity\ProfileUpdateRequest;
use App\Repository\ProfileUpdateRequestRepository;
use App\Service\ProfileUpdateRequestManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/profile-update-requests')]
#[IsGranted('ROLE_ADMIN')]
class ProfileUpdateRequestController extends AbstractController
{
    public function __construct(
        private ProfileUpdateRequestManager $profileUpdateRequestManager,
        private ProfileUpdateRequestRepository $profileUpdateRequestRepository
    ) {
    }

    /**
     * Liste des demandes de modification de profil
     */
    #[Route('', name: 'app_admin_profile_update_requests_list', methods: ['GET'])]
    public function list(Request $request): Response
    {
        $status = $request->query->get('status', ProfileUpdateRequest::STATUS_PENDING);

        $queryBuilder = $this->profileUpdateRequestRepository->createQueryBuilder('pur')
            ->leftJoin('pur.user', 'u')
            ->orderBy('pur.requestedAt', 'DESC');

        if ($status) {
            $queryBuilder
                ->andWhere('pur.status = :status')
                ->setParameter('status', $status);
        }

        $requests = $queryBuilder->getQuery()->getResult();

        // Compter les demandes en attente
        $pendingCount = $this->profileUpdateRequestRepository->countPending();

        return $this->render('admin/profile_update_requests/list.html.twig', [
            'requests' => $requests,
            'currentStatus' => $status,
            'pendingCount' => $pendingCount,
        ]);
    }

    /**
     * Approuver une demande de modification
     */
    #[Route('/{id}/approve', name: 'app_admin_profile_update_requests_approve', methods: ['POST'])]
    public function approve(ProfileUpdateRequest $profileUpdateRequest): Response
    {
        $this->denyAccessUnlessGranted('PROFILE_UPDATE_REQUEST_APPROVE', $profileUpdateRequest);

        try {
            $this->profileUpdateRequestManager->approveRequest($profileUpdateRequest, $this->getUser());

            $this->addFlash('success', sprintf(
                'Demande de modification approuvée. Le profil de %s a été mis à jour.',
                $profileUpdateRequest->getUser()->getFullName()
            ));
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur : ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_admin_profile_update_requests_list');
    }

    /**
     * Rejeter une demande de modification
     */
    #[Route('/{id}/reject', name: 'app_admin_profile_update_requests_reject', methods: ['POST'])]
    public function reject(ProfileUpdateRequest $profileUpdateRequest, Request $request): Response
    {
        $this->denyAccessUnlessGranted('PROFILE_UPDATE_REQUEST_REJECT', $profileUpdateRequest);

        $reason = $request->request->get('reason');

        if (!$reason) {
            $this->addFlash('error', 'La raison du rejet est obligatoire.');
            return $this->redirectToRoute('app_admin_profile_update_requests_list');
        }

        try {
            $this->profileUpdateRequestManager->rejectRequest($profileUpdateRequest, $reason, $this->getUser());

            $this->addFlash('success', sprintf(
                'Demande de modification rejetée. %s a été notifié.',
                $profileUpdateRequest->getUser()->getFullName()
            ));
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur : ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_admin_profile_update_requests_list');
    }

    /**
     * Voir les détails d'une demande
     */
    #[Route('/{id}', name: 'app_admin_profile_update_requests_view', methods: ['GET'])]
    public function view(ProfileUpdateRequest $profileUpdateRequest): Response
    {
        $this->denyAccessUnlessGranted('PROFILE_UPDATE_REQUEST_VIEW', $profileUpdateRequest);

        return $this->render('admin/profile_update_requests/view.html.twig', [
            'request' => $profileUpdateRequest,
        ]);
    }
}
