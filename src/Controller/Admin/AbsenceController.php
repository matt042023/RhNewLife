<?php

namespace App\Controller\Admin;

use App\Entity\Absence;
use App\Entity\Document;
use App\Form\AbsenceFilterType;
use App\Form\AbsenceValidationType;
use App\Repository\TypeAbsenceRepository;
use App\Service\Absence\AbsenceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/absences')]
#[IsGranted('ROLE_ADMIN')]
class AbsenceController extends AbstractController
{
    public function __construct(
        private AbsenceService $absenceService,
        private TypeAbsenceRepository $typeAbsenceRepository,
        private \App\Repository\AbsenceRepository $absenceRepository
    ) {
    }

    /**
     * Dashboard - List all absences with filters
     */
    #[Route('', name: 'admin_absence_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $filterForm = $this->createForm(AbsenceFilterType::class);
        $filterForm->handleRequest($request);

        $filters = [];
        if ($filterForm->isSubmitted() && $filterForm->isValid()) {
            $filters = $filterForm->getData();
        }

        // Get all absences sorted (pending first) and filtered
        $absences = $this->absenceRepository->findAllSorted($filters);

        // Get absences with overdue justifications
        $overdueJustifications = $this->absenceService->getAbsencesWithOverdueJustifications();

        return $this->render('admin/absence/index.html.twig', [
            'absences' => $absences,
            'overdueJustifications' => $overdueJustifications,
            'filterForm' => $filterForm->createView(),
        ]);
    }

    /**
     * Show absence details with validation form
     */
    #[Route('/{id}', name: 'admin_absence_show', methods: ['GET', 'POST'])]
    public function show(Absence $absence, Request $request): Response
    {
        $validationForm = $this->createForm(AbsenceValidationType::class);
        $validationForm->handleRequest($request);

        if ($validationForm->isSubmitted() && $validationForm->isValid()) {
            $data = $validationForm->getData();
            $action = $data['action'];

            try {
                if ($action === 'validate') {
                    // Set admin comment if provided
                    if (!empty($data['adminComment'])) {
                        $absence->setAdminComment($data['adminComment']);
                    }

                    $forceWithoutJustification = $data['forceWithoutJustification'] ?? false;

                    $this->absenceService->validateAbsence(
                        $absence,
                        $this->getUser(),
                        $forceWithoutJustification
                    );

                    $this->addFlash('success', 'Absence validée avec succès.');
                } else {
                    // Reject action
                    $rejectionReason = $data['rejectionReason'];

                    if (empty($rejectionReason)) {
                        $this->addFlash('error', 'Le motif de refus est obligatoire');
                        return $this->redirectToRoute('admin_absence_show', ['id' => $absence->getId()]);
                    }

                    $this->absenceService->rejectAbsence($absence, $this->getUser(), $rejectionReason);
                    $this->addFlash('success', 'Absence refusée.');
                }

                return $this->redirectToRoute('admin_absence_show', ['id' => $absence->getId()]);
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur : ' . $e->getMessage());
            }
        }

        return $this->render('admin/absence/show.html.twig', [
            'absence' => $absence,
            'validationForm' => $validationForm->createView(),
        ]);
    }


    /**
     * Validate justification document
     */
    #[Route('/{id}/justificatif/{docId}/valider', name: 'admin_absence_justification_validate', methods: ['POST'])]
    public function validateJustification(Absence $absence, int $docId, Request $request): Response
    {
        $document = $this->findDocument($absence, $docId);

        if (!$document) {
            throw $this->createNotFoundException('Justificatif non trouvé');
        }

        $comment = $request->request->get('comment');

        try {
            $this->absenceService->validateJustification($document, $this->getUser(), $comment);
            $this->addFlash('success', 'Justificatif validé avec succès.');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur : ' . $e->getMessage());
        }

        return $this->redirectToRoute('admin_absence_show', ['id' => $absence->getId()]);
    }

    /**
     * Reject justification document
     */
    #[Route('/{id}/justificatif/{docId}/rejeter', name: 'admin_absence_justification_reject', methods: ['POST'])]
    public function rejectJustification(Absence $absence, int $docId, Request $request): Response
    {
        $document = $this->findDocument($absence, $docId);

        if (!$document) {
            throw $this->createNotFoundException('Justificatif non trouvé');
        }

        $reason = $request->request->get('rejection_reason');

        if (empty($reason)) {
            $this->addFlash('error', 'Le motif de rejet est obligatoire');
            return $this->redirectToRoute('admin_absence_show', ['id' => $absence->getId()]);
        }

        try {
            $this->absenceService->rejectJustification($document, $this->getUser(), $reason);
            $this->addFlash('success', 'Justificatif rejeté. Une nouvelle échéance a été définie.');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur : ' . $e->getMessage());
        }

        return $this->redirectToRoute('admin_absence_show', ['id' => $absence->getId()]);
    }

    /**
     * View justification document
     */
    #[Route('/{id}/justificatif/{docId}', name: 'admin_absence_justification_view', methods: ['GET'])]
    public function viewJustification(Absence $absence, int $docId): Response
    {
        $document = $this->findDocument($absence, $docId);

        if (!$document) {
            throw $this->createNotFoundException('Justificatif non trouvé');
        }

        // TODO: Implement file serving
        return $this->redirectToRoute('admin_absence_show', ['id' => $absence->getId()]);
    }

    /**
     * List pending justifications
     */
    #[Route('/justificatifs-en-attente', name: 'admin_absence_justifications_pending', methods: ['GET'])]
    public function pendingJustifications(): Response
    {
        // Get all absences with documents pending validation
        // TODO: Create dedicated repository method for this
        $pendingJustifications = [];

        return $this->render('admin/absence/justifications_pending.html.twig', [
            'pendingJustifications' => $pendingJustifications,
        ]);
    }

    /**
     * Calendar view of absences
     */
    #[Route('/calendrier', name: 'admin_absence_calendar', methods: ['GET'])]
    public function calendar(): Response
    {
        // TODO: Implement in Phase 6 with FullCalendar
        return $this->render('admin/absence/calendar.html.twig', []);
    }

    /**
     * Export absences to CSV/Excel
     */
    #[Route('/export', name: 'admin_absence_export', methods: ['GET'])]
    public function export(Request $request): Response
    {
        // TODO: Implement export service
        $this->addFlash('info', 'Export à implémenter');
        return $this->redirectToRoute('admin_absence_index');
    }

    /**
     * Create absence manually (admin)
     * TODO: Implement with form in Phase 4
     */
    #[Route('/creer', name: 'admin_absence_create', methods: ['GET', 'POST'])]
    public function create(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $this->addFlash('info', 'Création manuelle à implémenter en Phase 4');
            return $this->redirectToRoute('admin_absence_index');
        }

        return $this->render('admin/absence/create.html.twig', [
            'absenceTypes' => $this->typeAbsenceRepository->findActive(),
        ]);
    }

    /**
     * Helper to find document in absence documents collection
     */
    private function findDocument(Absence $absence, int $docId): ?Document
    {
        foreach ($absence->getDocuments() as $doc) {
            if ($doc->getId() === $docId) {
                return $doc;
            }
        }

        return null;
    }
}
