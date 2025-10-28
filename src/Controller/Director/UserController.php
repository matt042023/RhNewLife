<?php

namespace App\Controller\Director;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/director/users')]
#[IsGranted('ROLE_DIRECTOR')]
class UserController extends AbstractController
{
    public function __construct(
        private UserRepository $userRepository
    ) {
    }

    /**
     * Liste des utilisateurs (lecture seule pour directeurs)
     */
    #[Route('', name: 'app_director_users_list', methods: ['GET'])]
    public function list(Request $request): Response
    {
        $status = $request->query->get('status', 'active');
        $search = $request->query->get('search', '');

        $qb = $this->userRepository->createQueryBuilder('u');

        // Filtrer par statut
        if ($status === 'active') {
            $qb->where('u.status = :status')
               ->setParameter('status', User::STATUS_ACTIVE);
        } elseif ($status === 'archived') {
            $qb->where('u.status = :status')
               ->setParameter('status', User::STATUS_ARCHIVED);
        } elseif ($status === 'onboarding') {
            $qb->where('u.status = :status')
               ->setParameter('status', User::STATUS_ONBOARDING);
        } elseif ($status === 'invited') {
            $qb->where('u.status = :status')
               ->setParameter('status', User::STATUS_INVITED);
        }

        // Recherche
        if (!empty($search)) {
            $qb->andWhere('u.firstName LIKE :search OR u.lastName LIKE :search OR u.email LIKE :search OR u.matricule LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }

        $qb->orderBy('u.lastName', 'ASC')
           ->addOrderBy('u.firstName', 'ASC');

        $users = $qb->getQuery()->getResult();

        // Statistiques
        $stats = [
            'total' => $this->userRepository->count([]),
            'active' => $this->userRepository->count(['status' => User::STATUS_ACTIVE]),
            'onboarding' => $this->userRepository->count(['status' => User::STATUS_ONBOARDING]),
            'invited' => $this->userRepository->count(['status' => User::STATUS_INVITED]),
            'archived' => $this->userRepository->count(['status' => User::STATUS_ARCHIVED]),
        ];

        return $this->render('director/users/list.html.twig', [
            'users' => $users,
            'stats' => $stats,
            'currentStatus' => $status,
            'search' => $search,
        ]);
    }

    /**
     * Voir le détail d'un utilisateur (lecture seule pour directeurs)
     */
    #[Route('/{id}', name: 'app_director_users_view', methods: ['GET'])]
    public function view(User $user): Response
    {
        // Récupérer le contrat actif
        $activeContract = $user->getActiveContract();

        // Récupérer tous les contrats
        $allContracts = $user->getContracts()->toArray();

        // Récupérer les documents
        $documents = $user->getDocuments()->toArray();

        return $this->render('director/users/view.html.twig', [
            'user' => $user,
            'activeContract' => $activeContract,
            'allContracts' => $allContracts,
            'documents' => $documents,
        ]);
    }
}
