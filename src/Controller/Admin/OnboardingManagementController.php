<?php

namespace App\Controller\Admin;

use App\Entity\Invitation;
use App\Entity\User;
use App\Repository\InvitationRepository;
use App\Repository\UserRepository;
use App\Service\DocumentManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/onboarding')]
#[IsGranted('ROLE_ADMIN')]
class OnboardingManagementController extends AbstractController
{
    public function __construct(
        private InvitationRepository $invitationRepository,
        private UserRepository $userRepository,
        private DocumentManager $documentManager
    ) {
    }

    /**
     * Page unifiée de gestion des onboardings
     */
    #[Route('', name: 'app_admin_onboarding_list', methods: ['GET'])]
    public function list(Request $request): Response
    {
        $filter = $request->query->get('filter');
        $search = $request->query->get('search');

        // Récupère toutes les invitations avec leurs users associés
        $queryBuilder = $this->invitationRepository->createQueryBuilder('i')
            ->leftJoin('i.user', 'u')
            ->addSelect('u')
            ->orderBy('i.createdAt', 'DESC');

        // Filtre par recherche
        if ($search) {
            $queryBuilder
                ->andWhere('i.email LIKE :search OR i.firstName LIKE :search OR i.lastName LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        // Filtres par statut global
        switch ($filter) {
            case 'en_attente_activation':
                $queryBuilder->andWhere('i.status = :status')
                    ->setParameter('status', Invitation::STATUS_PENDING);
                break;

            case 'en_cours_onboarding':
                $queryBuilder
                    ->andWhere('i.status = :status')
                    ->andWhere('u.status = :userStatus')
                    ->andWhere('u.submittedAt IS NULL')
                    ->setParameter('status', Invitation::STATUS_USED)
                    ->setParameter('userStatus', User::STATUS_ONBOARDING);
                break;

            case 'a_valider':
                $queryBuilder
                    ->andWhere('i.status = :status')
                    ->andWhere('u.status = :userStatus')
                    ->andWhere('u.submittedAt IS NOT NULL')
                    ->setParameter('status', Invitation::STATUS_USED)
                    ->setParameter('userStatus', User::STATUS_ONBOARDING);
                break;

            case 'valides':
                $queryBuilder
                    ->andWhere('i.status = :status')
                    ->andWhere('u.status = :userStatus')
                    ->setParameter('status', Invitation::STATUS_USED)
                    ->setParameter('userStatus', User::STATUS_ACTIVE);
                break;

            case 'expires':
                $queryBuilder->andWhere('i.status = :status')
                    ->setParameter('status', Invitation::STATUS_EXPIRED);
                break;

            case 'erreur':
                $queryBuilder->andWhere('i.status = :status')
                    ->setParameter('status', Invitation::STATUS_ERROR);
                break;

            default:
                // Tous les statuts
                break;
        }

        $invitations = $queryBuilder->getQuery()->getResult();

        // Enrichir les données avec le statut de complétion pour chaque invitation
        $enrichedInvitations = [];
        foreach ($invitations as $invitation) {
            $data = [
                'invitation' => $invitation,
                'completionStatus' => null,
                'documentsCount' => 0,
            ];

            if ($invitation->getUser() && $invitation->getUser()->getStatus() === User::STATUS_ONBOARDING) {
                $completionStatus = $this->documentManager->getCompletionStatus($invitation->getUser());
                $data['completionStatus'] = $completionStatus;
                $data['documentsCount'] = $completionStatus['completed_required'];
            }

            $enrichedInvitations[] = $data;
        }

        // Calculer les statistiques
        $stats = $this->calculateStatistics($invitations);

        return $this->render('admin/onboarding/list.html.twig', [
            'invitations' => $enrichedInvitations,
            'stats' => $stats,
            'currentFilter' => $filter,
            'search' => $search,
        ]);
    }

    /**
     * Calculer les statistiques globales
     */
    private function calculateStatistics(array $invitations): array
    {
        $stats = [
            'total' => count($invitations),
            'en_attente_activation' => 0,
            'en_cours_onboarding' => 0,
            'a_valider' => 0,
            'valides' => 0,
            'expires' => 0,
            'erreur' => 0,
        ];

        foreach ($invitations as $invitation) {
            if ($invitation->getStatus() === Invitation::STATUS_PENDING) {
                $stats['en_attente_activation']++;
            } elseif ($invitation->getStatus() === Invitation::STATUS_EXPIRED) {
                $stats['expires']++;
            } elseif ($invitation->getStatus() === Invitation::STATUS_ERROR) {
                $stats['erreur']++;
            } elseif ($invitation->getStatus() === Invitation::STATUS_USED && $invitation->getUser()) {
                $user = $invitation->getUser();
                if ($user->getStatus() === User::STATUS_ONBOARDING) {
                    if ($user->isSubmitted()) {
                        $stats['a_valider']++;
                    } else {
                        $stats['en_cours_onboarding']++;
                    }
                } elseif ($user->getStatus() === User::STATUS_ACTIVE) {
                    $stats['valides']++;
                }
            }
        }

        return $stats;
    }
}
