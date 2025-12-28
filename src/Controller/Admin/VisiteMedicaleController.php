<?php

namespace App\Controller\Admin;

use App\Entity\Document;
use App\Entity\VisiteMedicale;
use App\Form\VisiteMedicaleFilterType;
use App\Form\VisiteMedicaleType;
use App\Form\VisiteMedicaleCompleteType;
use App\Repository\VisiteMedicaleRepository;
use App\Service\DocumentManager;
use App\Service\MedicalVisit\MedicalVisitService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/visites-medicales')]
#[IsGranted('ROLE_ADMIN')]
class VisiteMedicaleController extends AbstractController
{
    public function __construct(
        private VisiteMedicaleRepository $visiteMedicaleRepository,
        private MedicalVisitService $medicalVisitService,
        private DocumentManager $documentManager,
        private EntityManagerInterface $entityManager
    ) {
    }

    /**
     * List all medical visits with filters and statistics
     */
    #[Route('', name: 'admin_visite_medicale_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $this->denyAccessUnlessGranted('VISITE_MEDICALE_LIST');

        $filterForm = $this->createForm(VisiteMedicaleFilterType::class);
        $filterForm->handleRequest($request);

        $filters = [];
        if ($filterForm->isSubmitted() && $filterForm->isValid()) {
            $data = $filterForm->getData();
            $filters = array_filter($data, fn($value) => $value !== null && $value !== '');

            // Handle expiring soon filter
            if (isset($filters['expiringSoon'])) {
                if ($filters['expiringSoon'] === '1') {
                    $filters['expiringSoon'] = true;
                    unset($filters['expired']);
                } elseif ($filters['expiringSoon'] === '2') {
                    $filters['expired'] = true;
                    unset($filters['expiringSoon']);
                } else {
                    unset($filters['expiringSoon']);
                }
            }
        }

        // Get visits with filters
        $visites = $this->visiteMedicaleRepository->findAllSorted($filters);

        // Get statistics for dashboard cards
        $stats = $this->visiteMedicaleRepository->getStatistics();

        return $this->render('admin/visite_medicale/index.html.twig', [
            'visites' => $visites,
            'stats' => $stats,
            'filterForm' => $filterForm->createView(),
        ]);
    }

    /**
     * Create a new medical visit manually (exceptional case)
     */
    #[Route('/creer-manuel', name: 'admin_visite_medicale_create', methods: ['GET', 'POST'])]
    public function create(Request $request): Response
    {
        $this->denyAccessUnlessGranted('VISITE_MEDICALE_CREATE');

        $visite = new VisiteMedicale();
        $form = $this->createForm(VisiteMedicaleType::class, $visite);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                // Manual creation means the visit has already been done
                $visite->setStatus(VisiteMedicale::STATUS_EFFECTUEE);

                // Handle certificate file upload
                $certificateFile = $form->get('certificateFile')->getData();

                // Save the visit
                $this->visiteMedicaleRepository->save($visite, true);

                // Upload certificate if provided
                if ($certificateFile) {
                    $document = $this->documentManager->uploadDocument(
                        file: $certificateFile,
                        user: $visite->getUser(),
                        type: Document::TYPE_MEDICAL_CERTIFICATE,
                        uploadedBy: $this->getUser()
                    );

                    // Link document to visite medicale
                    $document->setVisiteMedicale($visite);
                    $this->entityManager->flush();
                }

                $this->addFlash('success', sprintf(
                    'Visite médicale créée avec succès pour %s.',
                    $visite->getUser()->getFullName()
                ));

                return $this->redirectToRoute('admin_visite_medicale_show', ['id' => $visite->getId()]);
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de la création : ' . $e->getMessage());
            }
        }

        return $this->render('admin/visite_medicale/create.html.twig', [
            'form' => $form->createView(),
        ], new Response(null, $form->isSubmitted() && !$form->isValid() ? 422 : 200));
    }

    /**
     * Show medical visit details
     */
    #[Route('/{id}', name: 'admin_visite_medicale_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(VisiteMedicale $visite): Response
    {
        $this->denyAccessUnlessGranted('VISITE_MEDICALE_VIEW', $visite);

        return $this->render('admin/visite_medicale/show.html.twig', [
            'visite' => $visite,
        ]);
    }

    /**
     * Complete a scheduled medical visit with results
     */
    #[Route('/{id}/completer', name: 'admin_visite_medicale_complete', methods: ['GET', 'POST'])]
    public function complete(VisiteMedicale $visite, Request $request): Response
    {
        $this->denyAccessUnlessGranted('VISITE_MEDICALE_EDIT', $visite);

        // Only programmee visits can be completed
        if (!$visite->isProgrammee()) {
            $this->addFlash('error', 'Seules les visites programmées peuvent être complétées.');
            return $this->redirectToRoute('admin_visite_medicale_show', ['id' => $visite->getId()]);
        }

        $form = $this->createForm(VisiteMedicaleCompleteType::class, $visite);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                // Get certificate file if uploaded
                $certificateFile = $form->get('certificateFile')->getData();

                // Prepare data for service
                $data = [
                    'visitDate' => $visite->getVisitDate(),
                    'expiryDate' => $visite->getExpiryDate(),
                    'aptitude' => $visite->getAptitude(),
                    'medicalOrganization' => $visite->getMedicalOrganization(),
                    'observations' => $visite->getObservations(),
                    'uploadedBy' => $this->getUser(),
                ];

                // Complete the visit via service
                $this->medicalVisitService->completeMedicalVisit($visite, $data, $certificateFile);

                $this->addFlash('success', 'Résultats enregistrés avec succès. Le salarié a été notifié.');

                return $this->redirectToRoute('admin_visite_medicale_show', ['id' => $visite->getId()]);
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de la complétion : ' . $e->getMessage());
            }
        }

        return $this->render('admin/visite_medicale/complete.html.twig', [
            'visite' => $visite,
            'form' => $form->createView(),
        ], new Response(null, $form->isSubmitted() && !$form->isValid() ? 422 : 200));
    }

    /**
     * Edit medical visit (only if effectuee)
     */
    #[Route('/{id}/modifier', name: 'admin_visite_medicale_edit', methods: ['GET', 'POST'])]
    public function edit(VisiteMedicale $visite, Request $request): Response
    {
        $this->denyAccessUnlessGranted('VISITE_MEDICALE_EDIT', $visite);

        // Only effectuee visits can be edited (use complete for programmee)
        if (!$visite->isEffectuee()) {
            $this->addFlash('error', 'Seules les visites effectuées peuvent être modifiées. Utilisez "Compléter" pour les visites programmées.');
            return $this->redirectToRoute('admin_visite_medicale_show', ['id' => $visite->getId()]);
        }

        $form = $this->createForm(VisiteMedicaleType::class, $visite, ['is_edit' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                // Handle certificate file upload
                $certificateFile = $form->get('certificateFile')->getData();

                // Save the visit
                $this->visiteMedicaleRepository->save($visite, true);

                // Upload new certificate if provided
                if ($certificateFile) {
                    $document = $this->documentManager->uploadDocument(
                        file: $certificateFile,
                        user: $visite->getUser(),
                        type: Document::TYPE_MEDICAL_CERTIFICATE,
                        uploadedBy: $this->getUser()
                    );

                    // Link document to visite medicale
                    $document->setVisiteMedicale($visite);
                    $this->entityManager->flush();
                }

                $this->addFlash('success', 'Visite médicale modifiée avec succès.');

                return $this->redirectToRoute('admin_visite_medicale_show', ['id' => $visite->getId()]);
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de la modification : ' . $e->getMessage());
            }
        }

        return $this->render('admin/visite_medicale/edit.html.twig', [
            'visite' => $visite,
            'form' => $form->createView(),
        ], new Response(null, $form->isSubmitted() && !$form->isValid() ? 422 : 200));
    }

    /**
     * Delete medical visit
     */
    #[Route('/{id}/supprimer', name: 'admin_visite_medicale_delete', methods: ['POST'])]
    public function delete(VisiteMedicale $visite, Request $request): Response
    {
        $this->denyAccessUnlessGranted('VISITE_MEDICALE_DELETE', $visite);

        // Verify CSRF token
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('delete_visite_' . $visite->getId(), $token)) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_visite_medicale_show', ['id' => $visite->getId()]);
        }

        try {
            $userName = $visite->getUser()->getFullName();
            $this->visiteMedicaleRepository->remove($visite, true);

            $this->addFlash('success', sprintf(
                'Visite médicale supprimée avec succès pour %s.',
                $userName
            ));
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de la suppression : ' . $e->getMessage());
        }

        return $this->redirectToRoute('admin_visite_medicale_index');
    }
}
