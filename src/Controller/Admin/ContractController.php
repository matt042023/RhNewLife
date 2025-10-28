<?php

namespace App\Controller\Admin;

use App\Entity\Contract;
use App\Entity\User;
use App\Repository\ContractRepository;
use App\Repository\UserRepository;
use App\Repository\DocumentRepository;
use App\Service\ContractManager;
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
        private DocumentRepository $documentRepository
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

        if ($request->isMethod('POST')) {
            $errors = [];

            try {
                $data = [
                    'type' => $request->request->get('type'),
                    'startDate' => new \DateTime($request->request->get('start_date')),
                    'baseSalary' => $request->request->get('base_salary'),
                    'activityRate' => $request->request->get('activity_rate', '1.00'),
                    'weeklyHours' => $request->request->get('weekly_hours'),
                    'villa' => $request->request->get('villa'),
                    'createdBy' => $this->getUser(),
                ];

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

                $contract = $this->contractManager->createContract($user, $data);

                $this->addFlash('success', 'Contrat créé avec succès en mode brouillon.');
                return $this->redirectToRoute('app_admin_contracts_view', ['id' => $contract->getId()]);
            } catch (\Exception $e) {
                $errors[] = 'Erreur : ' . $e->getMessage();
            }

            return $this->render(
                'admin/contracts/form.html.twig',
                [
                    'user' => $user,
                    'errors' => $errors,
                    'formData' => $request->request->all(),
                    'isEdit' => false,
                    'contract' => null,
                ],
                new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY)
            );
        }

        return $this->render('admin/contracts/form.html.twig', [
            'user' => $user,
            'errors' => [],
            'formData' => [],
            'isEdit' => false,
            'contract' => null,
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

        if ($request->isMethod('POST')) {
            $errors = [];

            try {
                $data = [
                    'type' => $request->request->get('type'),
                    'startDate' => new \DateTime($request->request->get('start_date')),
                    'baseSalary' => $request->request->get('base_salary'),
                    'activityRate' => $request->request->get('activity_rate', '1.00'),
                    'weeklyHours' => $request->request->get('weekly_hours'),
                    'villa' => $request->request->get('villa'),
                ];

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
    #[Route('/{id}/create-amendment', name: 'app_admin_contracts_create_amendment', methods: ['GET', 'POST'])]
    public function createAmendment(Contract $contract, Request $request): Response
    {
        $this->denyAccessUnlessGranted('CONTRACT_CREATE_AMENDMENT', $contract);

        if ($request->isMethod('POST')) {
            $errors = [];

            try {
                $data = [
                    'type' => $request->request->get('type'),
                    'startDate' => new \DateTime($request->request->get('start_date')),
                    'baseSalary' => $request->request->get('base_salary'),
                    'activityRate' => $request->request->get('activity_rate', '1.00'),
                    'weeklyHours' => $request->request->get('weekly_hours'),
                    'villa' => $request->request->get('villa'),
                ];

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

                $reason = $request->request->get('amendment_reason');

                $amendment = $this->contractManager->createAmendment($contract, $data, $reason);

                $this->addFlash('success', sprintf('Avenant créé avec succès (version %d).', $amendment->getVersion()));
                return $this->redirectToRoute('app_admin_contracts_view', ['id' => $amendment->getId()]);
            } catch (\Exception $e) {
                $errors[] = 'Erreur : ' . $e->getMessage();
            }

            return $this->render(
                'admin/contracts/amendment_form.html.twig',
                [
                    'contract' => $contract,
                    'errors' => $errors,
                    'formData' => $request->request->all(),
                ],
                new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY)
            );
        }

        return $this->render('admin/contracts/amendment_form.html.twig', [
            'contract' => $contract,
            'errors' => [],
            'formData' => [],
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
