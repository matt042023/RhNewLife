<?php

namespace App\Controller\Admin;

use App\Entity\Contract;
use App\Entity\User;
use App\Repository\ContractRepository;
use App\Repository\UserRepository;
use App\Repository\DocumentRepository;
use App\Repository\TemplateContratRepository;
use App\Repository\VillaRepository;
use App\Service\ContractManager;
use App\Service\ContractGeneratorService;
use App\Service\ContractSignatureService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/contracts')]
#[IsGranted('ROLE_ADMIN')]
class ContractController extends AbstractController
{
    public function __construct(
        private ContractManager $contractManager,
        private ContractRepository $contractRepository,
        private UserRepository $userRepository,
        private DocumentRepository $documentRepository,
        private TemplateContratRepository $templateContratRepository,
        private ContractGeneratorService $contractGenerator,
        private ContractSignatureService $signatureService,
        private VillaRepository $villaRepository
    ) {
    }

    /**
     * Formulaire de création d'un contrat pour un utilisateur
     */
    #[Route('/users/{userId}/create', name: 'app_admin_contracts_create', methods: ['GET', 'POST'])]
    public function create(int $userId, Request $request): Response
    {
        $user = $this->getUserOrThrow($userId);
        $this->denyAccessUnlessGranted('CONTRACT_CREATE', $user);

        // Gérer le cas de l'avenant : récupérer le contrat parent si présent
        $parentContract = null;
        $parentId = $request->query->getInt('parentId');

        if ($parentId) {
            $parentContract = $this->contractRepository->find($parentId);
            if (!$parentContract) {
                $this->addFlash('error', 'Contrat parent introuvable.');
                return $this->redirectToRoute('app_admin_users_view', ['id' => $userId]);
            }

            // Vérifier que le parent appartient bien à cet utilisateur
            if ($parentContract->getUser()->getId() !== $userId) {
                $this->addFlash('error', 'Le contrat parent n\'appartient pas à cet utilisateur.');
                return $this->redirectToRoute('app_admin_users_view', ['id' => $userId]);
            }

            // Vérifier les permissions pour créer un avenant
            $this->denyAccessUnlessGranted('CONTRACT_CREATE_AMENDMENT', $parentContract);
        }

        // Récupérer les templates actifs
        $activeTemplates = $this->templateContratRepository->findActiveTemplates();

        // Récupérer toutes les villas pour le dropdown
        $villas = $this->villaRepository->findAll();

        // Si aucun template n'existe, rediriger vers la création de template
        if (empty($activeTemplates) && !$request->isMethod('POST')) {
            $this->addFlash('warning', 'Aucun modèle de contrat disponible. Veuillez d\'abord créer un modèle de contrat.');
            return $this->redirectToRoute('app_admin_contract_templates_create');
        }

        if ($request->isMethod('POST')) {
            $errors = [];

            try {
                // Récupérer le template sélectionné
                $templateId = $request->request->get('template_id');
                if (!$templateId) {
                    throw new \RuntimeException('Veuillez sélectionner un modèle de contrat.');
                }

                $template = $this->templateContratRepository->find($templateId);
                if (!$template) {
                    throw new \RuntimeException('Modèle de contrat introuvable.');
                }

                if (!$template->isActive()) {
                    throw new \RuntimeException('Ce modèle de contrat n\'est pas actif.');
                }

                // Détecter le système de suivi choisi
                $useAnnualDaySystem = (bool)$request->request->get('use_annual_day_system', false);

                $data = [
                    'type' => $request->request->get('type'),
                    'startDate' => new \DateTime($request->request->get('start_date')),
                    'baseSalary' => $request->request->get('base_salary'),
                    'createdBy' => $this->getUser(),
                    'useAnnualDaySystem' => $useAnnualDaySystem,
                ];

                // Gestion selon le système choisi
                if ($useAnnualDaySystem) {
                    // Système annuel: toujours 258 jours
                    $data['annualDaysRequired'] = '258.00';
                    $data['annualDayNotes'] = $request->request->get('annual_day_notes');
                } else {
                    // Système horaire classique
                    $data['activityRate'] = $request->request->get('activity_rate', '1.00');
                    $data['weeklyHours'] = $request->request->get('weekly_hours');
                }

                // Gérer la villa (relation vers entité)
                $villaId = $request->request->get('villa_id');
                if ($villaId) {
                    $villa = $this->villaRepository->find($villaId);
                    if ($villa) {
                        $data['villa'] = $villa;
                    }
                }

                // Champs optionnels
                $endDate = $request->request->get('end_date');
                if ($endDate) {
                    $data['endDate'] = new \DateTime($endDate);
                }

                $essaiEndDate = $request->request->get('essai_end_date');
                if ($essaiEndDate) {
                    $data['essaiEndDate'] = new \DateTime($essaiEndDate);
                }

                $workingDays = $request->request->all('working_days');
                if ($workingDays) {
                    $data['workingDays'] = $workingDays;
                }

                // Si c'est un avenant, ajouter les données de relation parent
                if ($parentContract) {
                    $data['parentContract'] = $parentContract;
                    $data['version'] = $parentContract->getVersion() + 1;
                }

                // Utiliser la méthode WF09 qui génère le PDF automatiquement
                $contract = $this->contractManager->createContractFromTemplate(
                    $user,
                    $template,
                    $data,
                    $this->contractGenerator
                );

                // Message de succès approprié
                if ($parentContract) {
                    $this->addFlash('success', sprintf('Avenant créé avec succès (version %d). Le document a été généré depuis le modèle.', $contract->getVersion()));
                } else {
                    $this->addFlash('success', 'Contrat créé avec succès en mode brouillon. Le document a été généré depuis le modèle.');
                }

                return $this->redirectToRoute('app_admin_contracts_view', ['id' => $contract->getId()]);
            } catch (\Exception $e) {
                $errors[] = 'Erreur : ' . $e->getMessage();
            }

            return $this->render(
                'admin/contracts/form.html.twig',
                [
                    'user' => $user,
                    'activeTemplates' => $activeTemplates,
                    'errors' => $errors,
                    'formData' => $request->request->all(),
                    'isEdit' => false,
                    'contract' => null,
                    'parentContract' => $parentContract,
                    'villas' => $villas,
                ],
                new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY)
            );
        }

        // Pré-remplir les données du parent si c'est un avenant
        $formData = [];
        if ($parentContract) {
            $formData = [
                'template_id' => $parentContract->getTemplate() ? $parentContract->getTemplate()->getId() : '',
                'type' => $parentContract->getType(),
                'start_date' => $parentContract->getStartDate() ? $parentContract->getStartDate()->format('Y-m-d') : '',
                'end_date' => $parentContract->getEndDate() ? $parentContract->getEndDate()->format('Y-m-d') : '',
                'essai_end_date' => $parentContract->getEssaiEndDate() ? $parentContract->getEssaiEndDate()->format('Y-m-d') : '',
                'base_salary' => $parentContract->getBaseSalary(),
                'prime' => $parentContract->getPrime(),
                'activity_rate' => $parentContract->getActivityRate(),
                'weekly_hours' => $parentContract->getWeeklyHours(),
                'villa_id' => $parentContract->getVilla() ? $parentContract->getVilla()->getId() : '',
                'mutuelle' => $parentContract->isMutuelle(),
                'prevoyance' => $parentContract->isPrevoyance(),
                'working_days' => $parentContract->getWorkingDays(),
            ];
        }

        return $this->render('admin/contracts/form.html.twig', [
            'user' => $user,
            'activeTemplates' => $activeTemplates,
            'errors' => [],
            'formData' => $formData,
            'isEdit' => false,
            'contract' => null,
            'parentContract' => $parentContract,
            'villas' => $villas,
        ]);
    }

    /**
     * Voir les détails d'un contrat
     */
    #[Route('/{id}', name: 'app_admin_contracts_view', methods: ['GET'])]
    public function view(Contract $contract): Response
    {
        $this->denyAccessUnlessGranted('CONTRACT_VIEW', $contract);

        return $this->render('admin/contracts/view.html.twig', [
            'contract' => $contract,
            'signedDocument' => $this->documentRepository->findSignedContractDocument($contract),
        ]);
    }

    /**
     * Formulaire d'édition d'un contrat (brouillon uniquement)
     */
    #[Route('/{id}/edit', name: 'app_admin_contracts_edit', methods: ['GET', 'POST'])]
    public function edit(Contract $contract, Request $request): Response
    {
        $this->denyAccessUnlessGranted('CONTRACT_EDIT', $contract);

        if ($contract->getStatus() !== Contract::STATUS_DRAFT) {
            $this->addFlash('error', 'Seuls les contrats en brouillon peuvent être modifiés. Créez un avenant pour modifier un contrat validé.');
            return $this->redirectToRoute('app_admin_contracts_view', ['id' => $contract->getId()]);
        }

        // Récupérer toutes les villas pour le dropdown
        $villas = $this->villaRepository->findAll();

        if ($request->isMethod('POST')) {
            $errors = [];

            try {
                $data = [
                    'type' => $request->request->get('type'),
                    'startDate' => new \DateTime($request->request->get('start_date')),
                    'baseSalary' => $request->request->get('base_salary'),
                    'activityRate' => $request->request->get('activity_rate', '1.00'),
                    'weeklyHours' => $request->request->get('weekly_hours'),
                ];

                // Gérer la villa (relation vers entité)
                $villaId = $request->request->get('villa_id');
                if ($villaId) {
                    $villa = $this->villaRepository->find($villaId);
                    if ($villa) {
                        $data['villa'] = $villa;
                    }
                }

                // Champs optionnels
                $endDate = $request->request->get('end_date');
                if ($endDate) {
                    $data['endDate'] = new \DateTime($endDate);
                }

                $essaiEndDate = $request->request->get('essai_end_date');
                if ($essaiEndDate) {
                    $data['essaiEndDate'] = new \DateTime($essaiEndDate);
                }

                $workingDays = $request->request->all('working_days');
                if ($workingDays) {
                    $data['workingDays'] = $workingDays;
                }

                // Mettre à jour les champs du contrat
                foreach ($data as $key => $value) {
                    $setter = 'set' . ucfirst($key);
                    if (method_exists($contract, $setter)) {
                        $contract->$setter($value);
                    }
                }

                $this->contractRepository->save($contract, true);

                $this->addFlash('success', 'Contrat mis à jour avec succès.');
                return $this->redirectToRoute('app_admin_contracts_view', ['id' => $contract->getId()]);
            } catch (\Exception $e) {
                $errors[] = 'Erreur : ' . $e->getMessage();
            }

            return $this->render(
                'admin/contracts/form.html.twig',
                [
                    'contract' => $contract,
                    'user' => $contract->getUser(),
                    'errors' => $errors,
                    'formData' => $request->request->all(),
                    'isEdit' => true,
                    'villas' => $villas,
                ],
                new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY)
            );
        }

        return $this->render('admin/contracts/form.html.twig', [
            'contract' => $contract,
            'user' => $contract->getUser(),
            'errors' => [],
            'formData' => [],
            'isEdit' => true,
            'villas' => $villas,
        ]);
    }

    /**
     * Valider un contrat brouillon
     */
    #[Route('/{id}/validate', name: 'app_admin_contracts_validate', methods: ['POST'])]
    public function validate(Contract $contract): Response
    {
        $this->denyAccessUnlessGranted('CONTRACT_VALIDATE', $contract);

        try {
            $this->contractManager->validateContract($contract, $this->getUser());
            $this->addFlash('success', 'Contrat validé avec succès.');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur : ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_admin_contracts_view', ['id' => $contract->getId()]);
    }

    #[Route('/{id}/download-signed', name: 'app_admin_contracts_download_signed', methods: ['GET'])]
    public function downloadSigned(Contract $contract): Response
    {
        $this->denyAccessUnlessGranted('CONTRACT_VIEW', $contract);

        $document = $this->documentRepository->findSignedContractDocument($contract);

        if (!$document) {
            $this->addFlash('error', 'Aucun contrat signé disponible pour ce contrat.');
            return $this->redirectToRoute('app_admin_contracts_view', ['id' => $contract->getId()]);
        }

        $basePath = $this->getParameter('kernel.project_dir') . '/var/uploads/';
        $filePath = $basePath . $document->getFileName();

        if (!is_file($filePath)) {
            $this->addFlash('error', 'Le fichier du contrat signé est introuvable.');
            return $this->redirectToRoute('app_admin_contracts_view', ['id' => $contract->getId()]);
        }

        return $this->file(
            $filePath,
            $document->getOriginalName() ?? sprintf('contrat-signe-%d.pdf', $contract->getId()),
            $document->getMimeType() ?: 'application/pdf'
        );
    }

    /**
     * Envoyer le contrat au bureau comptable
     */
    #[Route('/{id}/send-to-accounting', name: 'app_admin_contracts_send_to_accounting', methods: ['POST'])]
    public function sendToAccounting(Contract $contract): Response
    {
        $this->denyAccessUnlessGranted('CONTRACT_SEND_TO_ACCOUNTING', $contract);

        try {
            $this->contractManager->sendContractToAccounting($contract);
            $this->addFlash('success', 'Contrat envoyé au bureau comptable avec succès.');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur : ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_admin_contracts_view', ['id' => $contract->getId()]);
    }

    /**
     * Upload du contrat signé (PDF)
     */
    #[Route('/{id}/upload-signed', name: 'app_admin_contracts_upload_signed', methods: ['POST'])]
    public function uploadSigned(Contract $contract, Request $request): Response
    {
        $this->denyAccessUnlessGranted('CONTRACT_UPLOAD_SIGNED', $contract);

        /** @var UploadedFile $file */
        $file = $request->files->get('signed_contract');

        if (!$file) {
            $this->addFlash('error', 'Veuillez sélectionner un fichier.');
            return $this->redirectToRoute('app_admin_contracts_view', ['id' => $contract->getId()]);
        }

        // Vérifier que c'est un PDF
        if ($file->getClientMimeType() !== 'application/pdf') {
            $this->addFlash('error', 'Le fichier doit être au format PDF.');
            return $this->redirectToRoute('app_admin_contracts_view', ['id' => $contract->getId()]);
        }

        try {
            $originalName = $file->getClientOriginalName();
            $mimeType = $file->getClientMimeType() ?: 'application/pdf';
            $fileSize = $file->getSize();

            // Déplacer le fichier vers le répertoire d'upload
            $uploadDir = $this->getParameter('kernel.project_dir') . '/var/uploads/contracts';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $filename = sprintf(
                'contract_%d_%s.pdf',
                $contract->getId(),
                uniqid()
            );

            $file->move($uploadDir, $filename);
            $storedFileName = 'contracts/' . $filename;

            $this->contractManager->uploadSignedContract(
                $contract,
                $storedFileName,
                $originalName,
                $mimeType,
                $fileSize
            );

            $this->addFlash('success', 'Contrat signé uploadé avec succès.');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur : ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_admin_contracts_view', ['id' => $contract->getId()]);
    }


    /**
     * Formulaire de création d'un avenant
     */
    #[Route('/{id}/create-amendment', name: 'app_admin_contracts_create_amendment', methods: ['GET'])]
    public function createAmendment(Contract $contract): Response
    {
        $this->denyAccessUnlessGranted('CONTRACT_CREATE_AMENDMENT', $contract);

        // Rediriger vers le formulaire de création classique avec le parent en paramètre
        return $this->redirectToRoute('app_admin_contracts_create', [
            'userId' => $contract->getUser()->getId(),
            'parentId' => $contract->getId()
        ]);
    }

    /**
     * Formulaire de clôture d'un contrat
     */
    #[Route('/{id}/close', name: 'app_admin_contracts_close', methods: ['GET', 'POST'])]
    public function close(Contract $contract, Request $request): Response
    {
        $this->denyAccessUnlessGranted('CONTRACT_CLOSE', $contract);

        if ($request->isMethod('POST')) {
            $errors = [];

            try {
                $reason = $request->request->get('reason');
                $terminationDate = new \DateTime($request->request->get('termination_date'));

                if (!$reason) {
                    $errors[] = 'La raison de clôture est obligatoire.';
                }

                if (empty($errors)) {
                    $this->contractManager->closeContract($contract, $reason, $terminationDate);

                    $this->addFlash('success', 'Contrat clôturé avec succès.');
                    return $this->redirectToRoute('app_admin_users_view', ['id' => $contract->getUser()->getId()]);
                }
            } catch (\Exception $e) {
                $errors[] = 'Erreur : ' . $e->getMessage();
            }

            return $this->render(
                'admin/contracts/close_form.html.twig',
                [
                    'contract' => $contract,
                    'errors' => $errors,
                    'formData' => $request->request->all(),
                ],
                new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY)
            );
        }

        return $this->render('admin/contracts/close_form.html.twig', [
            'contract' => $contract,
            'errors' => [],
            'formData' => [],
        ]);
    }

    /**
     * Historique des contrats d'un utilisateur (timeline)
     */
    #[Route('/users/{userId}/history', name: 'app_admin_contracts_history', methods: ['GET'])]
    public function history(int $userId): Response
    {
        $user = $this->getUserOrThrow($userId);
        $this->denyAccessUnlessGranted('CONTRACT_VIEW', $user);

        $contracts = $this->contractManager->getContractHistory($user);

        return $this->render('admin/contracts/history.html.twig', [
            'user' => $user,
            'contracts' => $contracts,
        ]);
    }

    /**
     * Comparaison de deux versions de contrat
     */
    #[Route('/compare', name: 'app_admin_contracts_compare', methods: ['GET'])]
    public function compare(Request $request): Response
    {
        $contract1Id = $request->query->get('contract1');
        $contract2Id = $request->query->get('contract2');

        if (!$contract1Id || !$contract2Id) {
            $this->addFlash('error', 'Veuillez sélectionner deux contrats à comparer.');
            return $this->redirectToRoute('app_admin_users_list');
        }

        $contract1 = $this->contractRepository->find($contract1Id);
        $contract2 = $this->contractRepository->find($contract2Id);

        if (!$contract1 || !$contract2) {
            $this->addFlash('error', 'Contrat introuvable.');
            return $this->redirectToRoute('app_admin_users_list');
        }

        $this->denyAccessUnlessGranted('CONTRACT_VIEW', $contract1);
        $this->denyAccessUnlessGranted('CONTRACT_VIEW', $contract2);

        $diff = $this->contractManager->compareContractVersions($contract1, $contract2);

        return $this->render('admin/contracts/compare.html.twig', [
            'contract1' => $contract1,
            'contract2' => $contract2,
            'diff' => $diff,
        ]);
    }

    /**
     * Prévisualiser le PDF brouillon
     */
    #[Route('/{id}/preview', name: 'app_admin_contracts_preview', methods: ['GET'])]
    public function preview(Contract $contract): Response
    {
        $this->denyAccessUnlessGranted('CONTRACT_VIEW', $contract);

        $basePath = $this->getParameter('kernel.project_dir') . '/public/uploads/';

        // Priorité : afficher le PDF signé si disponible, sinon le brouillon
        $fileUrl = null;
        $fileType = null;

        if ($contract->getSignedFileUrl()) {
            // Le contrat a été signé, afficher le PDF signé
            $fileUrl = $contract->getSignedFileUrl();
            $fileType = 'signé';
            $filePath = $basePath . $fileUrl;

            if (!is_file($filePath)) {
                // Si le fichier signé n'existe pas, fallback sur le brouillon
                $fileUrl = $contract->getDraftFileUrl();
                $fileType = 'brouillon';
                $filePath = $basePath . $fileUrl;
            }
        } else {
            // Pas de PDF signé, afficher le brouillon
            $fileUrl = $contract->getDraftFileUrl();
            $fileType = 'brouillon';
            $filePath = $basePath . $fileUrl;
        }

        if (!$fileUrl) {
            $this->addFlash('error', 'Aucun fichier disponible pour ce contrat.');
            return $this->redirectToRoute('app_admin_contracts_view', ['id' => $contract->getId()]);
        }

        if (!is_file($filePath)) {
            $this->addFlash('error', 'Le fichier du contrat est introuvable.');
            return $this->redirectToRoute('app_admin_contracts_view', ['id' => $contract->getId()]);
        }

        return $this->render('admin/contracts/preview.html.twig', [
            'contract' => $contract,
            'fileUrl' => $fileUrl,
            'fileType' => $fileType,
        ]);
    }

    /**
     * Envoyer pour signature employé
     */
    #[Route('/{id}/send-for-signature', name: 'app_admin_contracts_send_for_signature', methods: ['POST'])]
    public function sendForSignature(Contract $contract): Response
    {
        $this->denyAccessUnlessGranted('CONTRACT_SEND_FOR_SIGNATURE', $contract);

        try {
            $this->signatureService->sendForSignature($contract);

            $this->addFlash('success', 'Contrat envoyé pour signature à ' . $contract->getUser()->getFullName());
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur : ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_admin_contracts_view', ['id' => $contract->getId()]);
    }

    /**
     * Valider un contrat signé
     */
    #[Route('/{id}/validate-signed', name: 'app_admin_contracts_validate_signed', methods: ['POST'])]
    public function validateSigned(Contract $contract): Response
    {
        $this->denyAccessUnlessGranted('CONTRACT_VALIDATE_SIGNED', $contract);

        try {
            $this->contractManager->validateSignedContract($contract, $this->getUser());
            $this->addFlash('success', 'Contrat validé et activé avec succès.');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur : ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_admin_contracts_view', ['id' => $contract->getId()]);
    }

    /**
     * Rejeter un contrat signé (uploadé) et demander un nouvel upload
     */
    #[Route('/{id}/reject-signed', name: 'app_admin_contracts_reject_signed', methods: ['POST'])]
    public function rejectSigned(Contract $contract, Request $request): Response
    {
        $this->denyAccessUnlessGranted('CONTRACT_VALIDATE_SIGNED', $contract);

        $reason = $request->request->get('rejection_reason');

        if (!$reason) {
            $this->addFlash('error', 'La raison du rejet est obligatoire.');
            return $this->redirectToRoute('app_admin_contracts_view', ['id' => $contract->getId()]);
        }

        try {
            $this->contractManager->rejectSignedContract($contract, $reason, $this->getUser());
            $this->addFlash('success', 'Le contrat a été rejeté. L\'employé a été notifié et pourra uploader à nouveau.');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur : ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_admin_contracts_view', ['id' => $contract->getId()]);
    }

    /**
     * Annuler un contrat
     */
    #[Route('/{id}/cancel', name: 'app_admin_contracts_cancel', methods: ['GET', 'POST'])]
    public function cancel(Contract $contract, Request $request): Response
    {
        $this->denyAccessUnlessGranted('CONTRACT_CANCEL', $contract);

        if ($request->isMethod('POST')) {
            $reason = $request->request->get('reason');

            if (!$reason) {
                $this->addFlash('error', 'La raison de l\'annulation est obligatoire.');
            } else {
                try {
                    $this->contractManager->cancelContract($contract, $reason);
                    $this->addFlash('success', 'Contrat annulé avec succès.');
                    return $this->redirectToRoute('app_admin_contracts_view', ['id' => $contract->getId()]);
                } catch (\Exception $e) {
                    $this->addFlash('error', 'Erreur : ' . $e->getMessage());
                }
            }
        }

        return $this->render('admin/contracts/cancel_form.html.twig', [
            'contract' => $contract,
        ]);
    }

    /**
     * Renvoyer email signature
     */
    #[Route('/{id}/resend-signature', name: 'app_admin_contracts_resend_signature', methods: ['POST'])]
    public function resendSignature(Contract $contract): Response
    {
        $this->denyAccessUnlessGranted('CONTRACT_RESEND_SIGNATURE', $contract);

        try {
            $this->signatureService->resendSignatureEmail($contract);

            $this->addFlash('success', 'Email de signature renvoyé avec succès.');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur : ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_admin_contracts_view', ['id' => $contract->getId()]);
    }

    /**
     * Liste contrats en attente validation
     */
    #[Route('/pending-validation', name: 'app_admin_contracts_pending_validation', methods: ['GET'])]
    public function pendingValidation(): Response
    {
        $contracts = $this->contractRepository->findPendingValidation();

        return $this->render('admin/contracts/pending_validation.html.twig', [
            'contracts' => $contracts,
        ]);
    }

    /**
     * Helper pour récupérer un utilisateur ou lever une exception
     */
    private function getUserOrThrow(int $userId): User
    {
        $user = $this->userRepository->find($userId);

        if (!$user) {
            throw $this->createNotFoundException('Utilisateur introuvable.');
        }

        return $user;
    }

}
