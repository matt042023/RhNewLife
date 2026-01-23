<?php

namespace App\Controller\Admin;

use App\Entity\Document;
use App\Entity\User;
use App\Repository\DocumentRepository;
use App\Repository\TemplateContratRepository;
use App\Repository\UserRepository;
use App\Repository\VillaRepository;
use App\Service\ContractManager;
use App\Service\DirectUserCreationService;
use App\Service\DirectUserCreationValidator;
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
        private VillaRepository $villaRepository,
        private DirectUserCreationService $directUserCreationService,
        private DirectUserCreationValidator $directUserCreationValidator,
        private TemplateContratRepository $templateContratRepository
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
     * Formulaire de création directe d'un utilisateur complet
     * Permet de créer un utilisateur avec tous ses documents et son contrat
     */
    #[Route('/create', name: 'app_admin_users_create', methods: ['GET', 'POST'])]
    public function create(Request $request): Response
    {
        $this->denyAccessUnlessGranted('USER_CREATE');

        if ($request->isMethod('POST')) {
            // Récupérer les données personnelles
            $personalData = [
                'email' => $request->request->get('email'),
                'firstName' => $request->request->get('first_name'),
                'lastName' => $request->request->get('last_name'),
                'position' => $request->request->get('position'),
                'phone' => $request->request->get('phone'),
                'address' => $request->request->get('address'),
                'familyStatus' => $request->request->get('family_status'),
                'children' => $request->request->get('children'),
                'iban' => $request->request->get('iban'),
                'bic' => $request->request->get('bic'),
                'hiringDate' => $request->request->get('hiring_date'),
                'color' => $request->request->get('color'),
                'mutuelleEnabled' => $request->request->get('mutuelle_enabled') === '1',
                'mutuelleNom' => $request->request->get('mutuelle_nom'),
                'mutuelleFormule' => $request->request->get('mutuelle_formule'),
                'mutuelleDateFin' => $request->request->get('mutuelle_date_fin'),
                'prevoyanceEnabled' => $request->request->get('prevoyance_enabled') === '1',
                'prevoyanceNom' => $request->request->get('prevoyance_nom'),
            ];

            // Ajouter la villa si fournie
            $villaId = $request->request->get('villa_id');
            if ($villaId) {
                $villa = $this->villaRepository->find($villaId);
                if ($villa) {
                    $personalData['villa'] = $villa;
                }
            }

            // Récupérer les fichiers de documents
            $documentFiles = [
                'cni' => $request->files->get('document_cni'),
                'rib' => $request->files->get('document_rib'),
                'domicile' => $request->files->get('document_domicile'),
                'honorabilite' => $request->files->get('document_honorabilite'),
                'diplome' => $request->files->get('document_diplome'),
                'contract_signed' => $request->files->get('contract_signed_file'),
            ];

            // Récupérer les données du contrat
            $contractData = [];
            if ($request->request->get('create_contract') === '1') {
                $contractData = [
                    'type' => $request->request->get('contract_type'),
                    'startDate' => $request->request->get('contract_start_date'),
                    'endDate' => $request->request->get('contract_end_date'),
                    'essaiEndDate' => $request->request->get('contract_essai_end_date'),
                    'baseSalary' => $request->request->get('contract_base_salary'),
                    'useAnnualDaySystem' => $request->request->get('contract_use_annual_day_system') === '1',
                    'annualDaysRequired' => $request->request->get('contract_annual_days_required'),
                ];

                // Ajouter la villa du contrat si différente
                $contractVillaId = $request->request->get('contract_villa_id');
                if ($contractVillaId) {
                    $contractVilla = $this->villaRepository->find($contractVillaId);
                    if ($contractVilla) {
                        $contractData['villa'] = $contractVilla;
                    }
                }
            }

            // Récupérer les options d'activation
            $activationMode = $request->request->get('activation_mode', 'email');
            $temporaryPassword = $request->request->get('temporary_password');
            $documentsRequired = $request->request->get('documents_required', '1') === '1';

            // Validation complète
            $validation = $this->directUserCreationValidator->validateAll(
                $personalData,
                $documentFiles,
                $contractData,
                $activationMode,
                $temporaryPassword,
                $documentsRequired
            );

            if (!$validation['valid']) {
                // Stocker les erreurs et les données pour ré-afficher le formulaire
                $request->getSession()->set('create_user_form_data', $request->request->all());
                $request->getSession()->set('create_user_form_errors', $validation['errors']);
                return $this->redirectToRoute('app_admin_users_create');
            }

            try {
                // Créer l'utilisateur complet
                $result = $this->directUserCreationService->createCompleteUser(
                    $personalData,
                    $documentFiles,
                    !empty($contractData) ? $contractData : null,
                    $activationMode,
                    $temporaryPassword,
                    $this->getUser()
                );

                // Messages de succès
                $user = $result->getUser();
                $successMessage = sprintf(
                    'Salarié créé avec succès. Matricule: %s.',
                    $user->getMatricule()
                );

                if ($result->hasContract()) {
                    $successMessage .= ' Contrat créé.';
                }

                if ($result->isActivationEmailSent()) {
                    $successMessage .= ' Email d\'activation envoyé.';
                } elseif ($activationMode === 'password') {
                    $successMessage .= ' Compte activé avec mot de passe temporaire.';
                }

                $this->addFlash('success', $successMessage);

                // Ajouter les warnings s'il y en a
                foreach ($result->getWarnings() as $warning) {
                    $this->addFlash('warning', $warning);
                }

                return $this->redirectToRoute('app_admin_users_view', ['id' => $user->getId()]);

            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de la création : ' . $e->getMessage());
                $request->getSession()->set('create_user_form_data', $request->request->all());
                return $this->redirectToRoute('app_admin_users_create');
            }
        }

        // Récupérer les données du formulaire depuis la session si elles existent
        $formData = $request->getSession()->get('create_user_form_data', []);
        $request->getSession()->remove('create_user_form_data');

        // Récupérer les erreurs depuis la session
        $formErrors = $request->getSession()->get('create_user_form_errors', []);
        $request->getSession()->remove('create_user_form_errors');

        // Récupérer les erreurs depuis les flash messages
        $flashErrors = [];
        foreach ($this->container->get('request_stack')->getSession()->getFlashBag()->get('error', []) as $error) {
            $flashErrors[] = $error;
        }

        return $this->render('admin/users/create.html.twig', [
            'formData' => $formData,
            'formErrors' => $formErrors,
            'flashErrors' => $flashErrors,
            'villas' => $this->villaRepository->findAll(),
            'contractTypes' => ['CDI', 'CDD', 'Stage', 'Alternance', 'Autre'],
            'templateContrats' => $this->templateContratRepository->findAll(),
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

                // Ajouter la couleur
                $color = $request->request->get('color');
                if ($color) {
                    $data['color'] = $color;
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
