<?php

namespace App\Controller\Admin;

use App\Entity\Invitation;
use App\Repository\InvitationRepository;
use App\Service\InvitationManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/invitations')]
#[IsGranted('ROLE_ADMIN')]
class InvitationController extends AbstractController
{
    public function __construct(
        private InvitationManager $invitationManager,
        private InvitationRepository $invitationRepository
    ) {
    }

    /**
     * Liste des invitations
     */
    #[Route('', name: 'app_admin_invitations_list', methods: ['GET'])]
    public function list(Request $request): Response
    {
        $status = $request->query->get('status');
        $search = $request->query->get('search');

        $queryBuilder = $this->invitationRepository->createQueryBuilder('i')
            ->orderBy('i.createdAt', 'DESC');

        if ($status) {
            $queryBuilder
                ->andWhere('i.status = :status')
                ->setParameter('status', $status);
        }

        if ($search) {
            $queryBuilder
                ->andWhere('i.email LIKE :search OR i.firstName LIKE :search OR i.lastName LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        $invitations = $queryBuilder->getQuery()->getResult();

        return $this->render('admin/invitations/list.html.twig', [
            'invitations' => $invitations,
            'currentStatus' => $status,
            'search' => $search,
        ]);
    }

    /**
     * Créer une nouvelle invitation
     */
    #[Route('/create', name: 'app_admin_invitations_create', methods: ['GET', 'POST'])]
    public function create(Request $request): Response
    {
        $this->denyAccessUnlessGranted('INVITATION_CREATE');

        if ($request->isMethod('POST')) {
            $email = $request->request->get('email');
            $firstName = $request->request->get('first_name');
            $lastName = $request->request->get('last_name');
            $position = $request->request->get('position');
            $structure = $request->request->get('structure');

            $errors = [];

            // Validation basique
            if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Email invalide.';
            }

            if (!$firstName || !$lastName) {
                $errors[] = 'Le nom et le prénom sont obligatoires.';
            }

            if (empty($errors)) {
                try {
                    $invitation = $this->invitationManager->createInvitation(
                        $email,
                        $firstName,
                        $lastName,
                        $position,
                        $structure
                    );

                    $this->addFlash('success', 'Invitation envoyée à ' . $email);
                    return $this->redirectToRoute('app_admin_invitations_list');
                } catch (\Exception $e) {
                    $errors[] = 'Erreur : ' . $e->getMessage();
                }
            }

            return $this->render('admin/invitations/create.html.twig', [
                'errors' => $errors,
                'formData' => $request->request->all(),
            ]);
        }

        return $this->render('admin/invitations/create.html.twig', [
            'errors' => [],
            'formData' => [],
        ]);
    }

    /**
     * Relancer une invitation
     */
    #[Route('/{id}/resend', name: 'app_admin_invitations_resend', methods: ['POST'])]
    public function resend(Invitation $invitation): Response
    {
        $this->denyAccessUnlessGranted('INVITATION_RESEND', $invitation);

        try {
            $this->invitationManager->resendInvitation($invitation);
            $this->addFlash('success', 'Invitation relancée avec succès.');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur : ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_admin_invitations_list');
    }

    /**
     * Supprimer une invitation
     */
    #[Route('/{id}/delete', name: 'app_admin_invitations_delete', methods: ['POST'])]
    public function delete(Invitation $invitation): Response
    {
        $this->denyAccessUnlessGranted('INVITATION_DELETE', $invitation);

        try {
            $em = $this->invitationRepository->getEntityManager();
            $em->remove($invitation);
            $em->flush();

            $this->addFlash('success', 'Invitation supprimée.');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de la suppression.');
        }

        return $this->redirectToRoute('app_admin_invitations_list');
    }

    /**
     * Voir les détails d'une invitation
     */
    #[Route('/{id}', name: 'app_admin_invitations_view', methods: ['GET'])]
    public function view(Invitation $invitation): Response
    {
        $this->denyAccessUnlessGranted('INVITATION_VIEW', $invitation);

        return $this->render('admin/invitations/view.html.twig', [
            'invitation' => $invitation,
        ]);
    }
}
