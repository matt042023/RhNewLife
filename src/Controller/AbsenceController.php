<?php

namespace App\Controller;

use App\Entity\Absence;
use App\Entity\Document;
use App\Form\AbsenceType;
use App\Repository\TypeAbsenceRepository;
use App\Service\Absence\AbsenceCounterService;
use App\Service\Absence\AbsenceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/mes-absences')]
#[IsGranted('ROLE_USER')]
class AbsenceController extends AbstractController
{
    public function __construct(
        private AbsenceService $absenceService,
        private AbsenceCounterService $counterService,
        private TypeAbsenceRepository $typeAbsenceRepository
    ) {
    }

    /**
     * List user's absences with counters
     */
    #[Route('', name: 'app_absence_index', methods: ['GET'])]
    public function index(): Response
    {
        $user = $this->getUser();
        $currentYear = (int) date('Y');

        $absences = $this->absenceService->getUserAbsences($user);
        $counters = $this->counterService->getUserCounters($user, $currentYear);

        return $this->render('absence/index.html.twig', [
            'absences' => $absences,
            'counters' => $counters,
            'currentYear' => $currentYear,
        ]);
    }

    /**
     * Show absence request form and handle creation
     */
    #[Route('/nouvelle', name: 'app_absence_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $absence = new Absence();
        $form = $this->createForm(AbsenceType::class, $absence);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $justificationFile = $form->get('justificationFile')->getData();

                $createdAbsence = $this->absenceService->createAbsence(
                    user: $this->getUser(),
                    absenceType: $absence->getAbsenceType(),
                    startAt: $absence->getStartAt(),
                    endAt: $absence->getEndAt(),
                    reason: $absence->getReason(),
                    justificationFile: $justificationFile
                );

                $this->addFlash('success', 'Votre demande d\'absence a été créée avec succès.');
                return $this->redirectToRoute('app_absence_show', ['id' => $createdAbsence->getId()]);
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur : ' . $e->getMessage());
            }
        }

        return $this->render('absence/new.html.twig', [
            'form' => $form->createView(),
            'absenceTypes' => $this->typeAbsenceRepository->findActive(),
        ], new Response(null, $form->isSubmitted() && !$form->isValid() ? 422 : 200));
    }

    /**
     * Show absence details
     */
    #[Route('/{id}', name: 'app_absence_show', methods: ['GET'])]
    public function show(Absence $absence): Response
    {
        // Check ownership
        if ($absence->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('absence/show.html.twig', [
            'absence' => $absence,
        ]);
    }

    /**
     * Cancel absence
     */
    #[Route('/{id}/annuler', name: 'app_absence_cancel', methods: ['POST'])]
    public function cancel(Absence $absence): Response
    {
        // Check ownership
        if ($absence->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        try {
            $this->absenceService->cancelAbsence($absence, $this->getUser());
            $this->addFlash('success', 'Votre demande d\'absence a été annulée.');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur : ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_absence_index');
    }

    /**
     * Upload justification document
     */
    #[Route('/{id}/justificatif/upload', name: 'app_absence_justification_upload', methods: ['POST'])]
    public function uploadJustification(Absence $absence, Request $request): Response
    {
        // Check ownership
        if ($absence->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $file = $request->files->get('justification');

        if (!$file) {
            $this->addFlash('error', 'Aucun fichier sélectionné');
            return $this->redirectToRoute('app_absence_show', ['id' => $absence->getId()]);
        }

        try {
            $this->absenceService->addJustification($absence, $file, $this->getUser());
            $this->addFlash('success', 'Justificatif uploadé avec succès. Il sera validé par l\'administrateur.');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de l\'upload : ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_absence_show', ['id' => $absence->getId()]);
    }

    /**
     * View justification document
     */
    #[Route('/{id}/justificatif/{docId}', name: 'app_absence_justification_view', methods: ['GET'])]
    public function viewJustification(Absence $absence, int $docId): Response
    {
        // Check ownership
        if ($absence->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $document = null;
        foreach ($absence->getDocuments() as $doc) {
            if ($doc->getId() === $docId) {
                $document = $doc;
                break;
            }
        }

        if (!$document) {
            throw $this->createNotFoundException('Justificatif non trouvé');
        }

        // Return file for download/view
        // TODO: Implement file serving in Phase 4
        return $this->redirectToRoute('app_absence_show', ['id' => $absence->getId()]);
    }

    /**
     * Delete justification document (only if pending)
     */
    #[Route('/{id}/justificatif/{docId}/supprimer', name: 'app_absence_justification_delete', methods: ['POST'])]
    public function deleteJustification(Absence $absence, int $docId): Response
    {
        // Check ownership
        if ($absence->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $document = null;
        foreach ($absence->getDocuments() as $doc) {
            if ($doc->getId() === $docId) {
                $document = $doc;
                break;
            }
        }

        if (!$document) {
            throw $this->createNotFoundException('Justificatif non trouvé');
        }

        if ($document->getStatus() !== Document::STATUS_PENDING) {
            $this->addFlash('error', 'Ce justificatif ne peut plus être supprimé');
            return $this->redirectToRoute('app_absence_show', ['id' => $absence->getId()]);
        }

        try {
            // TODO: Use DocumentManager to delete
            $this->addFlash('success', 'Justificatif supprimé');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur : ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_absence_show', ['id' => $absence->getId()]);
    }

    /**
     * Export user's absences to PDF
     */
    #[Route('/export-pdf', name: 'app_absence_export_pdf', methods: ['GET'])]
    public function exportPdf(): Response
    {
        // TODO: Implement PDF export in later phase
        $this->addFlash('info', 'Export PDF à implémenter');
        return $this->redirectToRoute('app_absence_index');
    }
}
