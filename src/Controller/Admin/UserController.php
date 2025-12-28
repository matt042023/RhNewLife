<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Repository\DocumentRepository;
use App\Repository\UserRepository;
use App\Repository\VillaRepository;
use App\Service\ContractManager;
use App\Service\DocumentManager;
use App\Service\UserManager;
use App\Service\MatriculeGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/users')]
#[IsGranted('ROLE_ADMIN')]
class UserController extends AbstractController
{
    public function __construct(
        private UserManager $userManager,
        private UserRepository $userRepository,
        private MatriculeGenerator $matriculeGenerator,
        private DocumentRepository $documentRepository,
        private DocumentManager $documentManager,
        private ContractManager $contractManager,
        private VillaRepository $villaRepository
    ) {}

    /**
     * Liste des utilisateurs
     */
    #[Route('', name: 'app_admin_users_list', methods: ['GET'])]
    public function list(Request $request): Response
    {
        $status = $request->query->get('status');
        $search = $request->query->get('search');

        $queryBuilder = $this->userRepository->createQueryBuilder('u')
            ->orderBy('u.createdAt', 'DESC');

        if ($status) {
            $queryBuilder
                ->andWhere('u.status = :status')
                ->setParameter('status', $status);
        }

        if ($search) {
            $queryBuilder
                ->andWhere('u.email LIKE :search OR u.firstName LIKE :search OR u.lastName LIKE :search OR u.matricule LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        $users = $queryBuilder->getQuery()->getResult();

        return $this->render('admin/users/list.html.twig', [
            'users' => $users,
            'currentStatus' => $status,
            'search' => $search,
        ]);
    }

    /**
     * Formulaire de création manuelle d'un utilisateur
     */
    #[Route('/create', name: 'app_admin_users_create', methods: ['GET', 'POST'])]
    public function create(Request $request): Response
    {
        $this->denyAccessUnlessGranted('USER_CREATE');

        if ($request->isMethod('POST')) {
            $email = $request->request->get('email');
            $firstName = $request->request->get('first_name');
            $lastName = $request->request->get('last_name');
            $position = $request->request->get('position');
            $villaId = $request->request->get('villa_id');
            $phone = $request->request->get('phone');
            $address = $request->request->get('address');
            $familyStatus = $request->request->get('family_status');
            $children = $request->request->get('children');
            $iban = $request->request->get('iban');
            $bic = $request->request->get('bic');
            $hiringDate = $request->request->get('hiring_date');
            $sendInvitation = $request->request->get('send_invitation', '1') === '1';

            $errors = [];

            // Validation basique
            if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Email invalide.';
            }

            // Vérifier si l'email existe déjà
            if ($email && $this->userRepository->findOneBy(['email' => $email])) {
                $errors[] = 'Cet email est déjà utilisé par un autre utilisateur.';
            }

            if (!$firstName || !$lastName) {
                $errors[] = 'Le nom et le prénom sont obligatoires.';
            }

            if (!$position) {
                $errors[] = 'Le poste est obligatoire.';
            }

            if (empty($errors)) {
                try {
                    $data = [
                        'email' => $email,
                        'firstName' => $firstName,
                        'lastName' => $lastName,
                        'position' => $position,
                    ];

                    // Ajouter les champs optionnels s'ils sont fournis
                    if ($phone) $data['phone'] = $phone;
                    if ($address) $data['address'] = $address;
                    if ($familyStatus) $data['familyStatus'] = $familyStatus;
                    if ($children) $data['children'] = $children;
                    if ($iban) $data['iban'] = $iban;
                    if ($bic) $data['bic'] = $bic;
                    if ($hiringDate) {
                        $data['hiringDate'] = new \DateTime($hiringDate);
                    }
                    if ($villaId) {
                        $villa = $this->villaRepository->find($villaId);
                        if ($villa) {
                            $data['villa'] = $villa;
                        }
                    }

                    $user = $this->userManager->createManualUser($data, $sendInvitation);

                    $this->addFlash('success', sprintf(
                        'Utilisateur créé avec succès. Matricule: %s. %s',
                        $user->getMatricule(),
                        $sendInvitation ? 'Invitation d\'activation envoyée.' : 'Aucune invitation envoyée.'
                    ));

                    return $this->redirectToRoute('app_admin_users_view', ['id' => $user->getId()]);
                } catch (\Exception $e) {
                    $errors[] = 'Erreur : ' . $e->getMessage();
                }
            }

            // S'il y a des erreurs, les stocker en flash et rediriger (requis par Turbo)
            if (!empty($errors)) {
                foreach ($errors as $error) {
                    $this->addFlash('error', $error);
                }
                // Stocker les données du formulaire en session pour les ré-afficher
                $request->getSession()->set('create_user_form_data', $request->request->all());
                return $this->redirectToRoute('app_admin_users_create');
            }
        }

        // Récupérer les données du formulaire depuis la session si elles existent
        $formData = $request->getSession()->get('create_user_form_data', []);
        $request->getSession()->remove('create_user_form_data');

        // Récupérer les erreurs depuis les flash messages
        $errors = [];
        foreach ($this->container->get('request_stack')->getSession()->getFlashBag()->get('error', []) as $error) {
            $errors[] = $error;
        }

        return $this->render('admin/users/create.html.twig', [
            'errors' => $errors,
            'formData' => $formData,
            'villas' => $this->villaRepository->findAll(),
        ]);
    }

    /**
     * Voir les détails d'un utilisateur
     */
    #[Route('/{id}', name: 'app_admin_users_view', methods: ['GET'])]
    public function view(User $user): Response
    {
        $this->denyAccessUnlessGranted('USER_VIEW', $user);

        // Récupérer le statut de complétion
        $completionStatus = $this->userManager->getUserCompletionStatus($user);

        // Récupérer les contrats
        $contracts = $user->getContracts();

        // Auto-créer les documents pour le contrat actif s'ils n'existent pas
        $activeContract = $user->getActiveContract();
        if ($activeContract) {
            $this->contractManager->createDocumentsForContract($activeContract, $this->getUser());
        }

        // Récupérer les documents actifs (non archivés)
        $documents = $this->documentRepository->findActiveByUser($user);

        // Préparer les documents par type pour l'affichage en grille
        $documentsByType = [];
        foreach ($documents as $document) {
            $documentsByType[$document->getType()][] = $document;
        }

        // Récupérer la complétion des documents avec catégories
        $completion = $this->documentManager->getCompletionStatus($user);

        return $this->render('admin/users/view.html.twig', [
            'user' => $user,
            'completionStatus' => $completionStatus,
            'contracts' => $contracts,
            'documents' => $documents,
            'documentsByType' => $documentsByType,
            'completion' => $completion,
            'categories' => $completion['categories'] ?? [],
        ]);
    }

    /**
     * Formulaire d'édition d'un utilisateur
     */
    #[Route('/{id}/edit', name: 'app_admin_users_edit', methods: ['GET', 'POST'])]
    public function edit(User $user, Request $request): Response
    {
        $this->denyAccessUnlessGranted('USER_EDIT', $user);

        if ($request->isMethod('POST')) {
            $errors = [];

            try {
                $data = [
                    'firstName' => $request->request->get('first_name'),
                    'lastName' => $request->request->get('last_name'),
                    'position' => $request->request->get('position'),
                    'phone' => $request->request->get('phone'),
                    'address' => $request->request->get('address'),
                    'familyStatus' => $request->request->get('family_status'),
                    'children' => $request->request->get('children'),
                    'iban' => $request->request->get('iban'),
                    'bic' => $request->request->get('bic'),
                    'mutuelleEnabled' => $request->request->get('mutuelle_enabled') === '1',
                    'mutuelleNom' => $request->request->get('mutuelle_nom'),
                    'mutuelleFormule' => $request->request->get('mutuelle_formule'),
                    'mutuelleDateFin' => $request->request->get('mutuelle_date_fin'),
                    'prevoyanceEnabled' => $request->request->get('prevoyance_enabled') === '1',
                    'prevoyanceNom' => $request->request->get('prevoyance_nom'),
                ];

                $hiringDate = $request->request->get('hiring_date');
                if ($hiringDate) {
                    $data['hiringDate'] = new \DateTime($hiringDate);
                }

                $villaId = $request->request->get('villa_id');
                if ($villaId) {
                    $villa = $this->villaRepository->find($villaId);
                    $data['villa'] = $villa;
                } else {
                    $data['villa'] = null;
                }

                $this->userManager->updateUserProfile($user, $data);

                $this->addFlash('success', 'Profil utilisateur mis à jour avec succès.');
                return $this->redirectToRoute('app_admin_users_view', ['id' => $user->getId()]);
            } catch (\Exception $e) {
                $errors[] = 'Erreur : ' . $e->getMessage();
            }

            // S'il y a des erreurs, les stocker en flash et rediriger (requis par Turbo)
            if (!empty($errors)) {
                foreach ($errors as $error) {
                    $this->addFlash('error', $error);
                }
                return $this->redirectToRoute('app_admin_users_edit', ['id' => $user->getId()]);
            }
        }

        // Récupérer les erreurs depuis les flash messages
        $errors = [];
        foreach ($this->container->get('request_stack')->getSession()->getFlashBag()->get('error', []) as $error) {
            $errors[] = $error;
        }

        return $this->render('admin/users/edit.html.twig', [
            'user' => $user,
            'errors' => $errors,
            'villas' => $this->villaRepository->findAll(),
        ]);
    }

    /**
     * Archiver un utilisateur
     */
    #[Route('/{id}/archive', name: 'app_admin_users_archive', methods: ['POST'])]
    public function archive(User $user, Request $request): Response
    {
        $this->denyAccessUnlessGranted('USER_ARCHIVE', $user);

        try {
            $reason = $request->request->get('reason');
            $this->userManager->archiveUser($user, $reason);

            $this->addFlash('success', 'Utilisateur archivé avec succès.');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur : ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_admin_users_list');
    }

    /**
     * Réactiver un utilisateur archivé
     */
    #[Route('/{id}/reactivate', name: 'app_admin_users_reactivate', methods: ['POST'])]
    public function reactivate(User $user): Response
    {
        $this->denyAccessUnlessGranted('USER_REACTIVATE', $user);

        try {
            $this->userManager->reactivateUser($user);

            $this->addFlash('success', 'Utilisateur réactivé avec succès.');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur : ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_admin_users_view', ['id' => $user->getId()]);
    }
}
