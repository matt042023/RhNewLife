<?php

namespace App\Controller\Admin;

use App\Entity\Astreinte;
use App\Form\AstreinteType;
use App\Repository\AstreinteRepository;
use App\Repository\UserRepository;
use App\Service\AstreinteManager;
use App\Service\Astreinte\AstreinteNotificationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/astreintes')]
#[IsGranted('ROLE_ADMIN')]
class AstreinteController extends AbstractController
{
    public function __construct(
        private AstreinteRepository $astreinteRepository,
        private UserRepository $userRepository,
        private AstreinteManager $astreinteManager,
        private AstreinteNotificationService $notificationService
    ) {}

    /**
     * Main dashboard: Monthly planning view
     */
    #[Route('', name: 'admin_astreinte_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        // Get current month or month from query params
        $year = (int) ($request->query->get('year') ?? date('Y'));
        $month = (int) ($request->query->get('month') ?? date('m'));

        // Get astreintes for this month
        $astreintes = $this->astreinteRepository->findByMonth($year, $month);

        // Get stats
        $stats = $this->astreinteRepository->getMonthStats($year, $month);

        // Get available educators (active status)
        $availableEducateurs = $this->userRepository->findBy(['status' => 'active']);
        $stats['availableEducateurs'] = count($availableEducateurs);

        // Month navigation
        $previousMonth = (new \DateTime("$year-$month-01"))->modify('-1 month');
        $nextMonth = (new \DateTime("$year-$month-01"))->modify('+1 month');

        $monthName = (new \DateTime("$year-$month-01"))->format('F Y');

        return $this->render('admin/astreinte/index.html.twig', [
            'astreintes' => $astreintes,
            'stats' => $stats,
            'year' => $year,
            'month' => $month,
            'monthName' => $monthName,
            'previousMonth' => $previousMonth,
            'nextMonth' => $nextMonth,
            'educateurs' => $availableEducateurs,
        ]);
    }

    /**
     * Generate weekly astreintes for a month
     */
    #[Route('/generer/{year}/{month}', name: 'admin_astreinte_generate', methods: ['POST'])]
    public function generate(int $year, int $month): Response
    {
        try {
            $astreintes = $this->astreinteManager->generateMonthlyAstreintes(
                $year,
                $month,
                $this->getUser()
            );

            $monthName = (new \DateTime("$year-$month-01"))->format('F Y');
            $this->addFlash('success', count($astreintes) . ' astreintes générées pour ' . $monthName);
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur : ' . $e->getMessage());
        }

        return $this->redirectToRoute('admin_astreinte_index', ['year' => $year, 'month' => $month]);
    }

    /**
     * Create single astreinte
     */
    #[Route('/creer', name: 'admin_astreinte_create', methods: ['GET', 'POST'])]
    public function create(Request $request): Response
    {
        $astreinte = new Astreinte();
        $form = $this->createForm(AstreinteType::class, $astreinte);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->astreinteManager->createAstreinte(
                    startAt: $astreinte->getStartAt(),
                    endAt: $astreinte->getEndAt(),
                    educateur: $astreinte->getEducateur(),
                    periodLabel: $astreinte->getPeriodLabel(),
                    createdBy: $this->getUser(),
                    notes: $astreinte->getNotes()
                );

                $this->addFlash('success', 'Astreinte créée avec succès.');
                return $this->redirectToRoute('admin_astreinte_index');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur : ' . $e->getMessage());
            }
        }

        return $this->render('admin/astreinte/create.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * Edit astreinte
     */
    #[Route('/{id}/modifier', name: 'admin_astreinte_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Astreinte $astreinte): Response
    {
        $form = $this->createForm(AstreinteType::class, $astreinte);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->astreinteManager->updateAstreinte(
                    astreinte: $astreinte,
                    startAt: $astreinte->getStartAt(),
                    endAt: $astreinte->getEndAt(),
                    educateur: $astreinte->getEducateur(),
                    periodLabel: $astreinte->getPeriodLabel(),
                    updatedBy: $this->getUser(),
                    notes: $astreinte->getNotes()
                );

                $this->addFlash('success', 'Astreinte modifiée avec succès.');
                return $this->redirectToRoute('admin_astreinte_index');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur : ' . $e->getMessage());
            }
        }

        return $this->render('admin/astreinte/edit.html.twig', [
            'form' => $form->createView(),
            'astreinte' => $astreinte,
        ]);
    }

    /**
     * Delete astreinte
     */
    #[Route('/{id}/supprimer', name: 'admin_astreinte_delete', methods: ['POST'])]
    public function delete(Request $request, Astreinte $astreinte): Response
    {
        if ($this->isCsrfTokenValid('delete' . $astreinte->getId(), $request->request->get('_token'))) {
            try {
                $this->astreinteManager->deleteAstreinte($astreinte);
                $this->addFlash('success', 'Astreinte supprimée.');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur : ' . $e->getMessage());
            }
        }

        return $this->redirectToRoute('admin_astreinte_index');
    }

    /**
     * AJAX: Assign educator to astreinte
     */
    #[Route('/{id}/assigner', name: 'admin_astreinte_assign', methods: ['POST'])]
    public function assign(Request $request, Astreinte $astreinte): JsonResponse
    {
        $educateurId = $request->request->get('educateur_id');

        $educateur = null;
        if ($educateurId) {
            $educateur = $this->userRepository->find($educateurId);
        }

        try {
            $this->astreinteManager->assignEducateur($astreinte, $educateur, $this->getUser());

            return new JsonResponse([
                'success' => true,
                'status' => $astreinte->getStatus(),
                'message' => $educateur
                    ? "Astreinte affectée à {$educateur->getFullName()}"
                    : "Affectation retirée"
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * AJAX: Clear assignment
     */
    #[Route('/{id}/retirer', name: 'admin_astreinte_clear', methods: ['POST'])]
    public function clear(Astreinte $astreinte): JsonResponse
    {
        try {
            $this->astreinteManager->assignEducateur($astreinte, null, $this->getUser());

            return new JsonResponse([
                'success' => true,
                'message' => 'Affectation retirée'
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * AJAX: Record replacement
     */
    #[Route('/{id}/remplacement', name: 'admin_astreinte_replacement', methods: ['POST'])]
    public function recordReplacement(Request $request, Astreinte $astreinte): JsonResponse
    {
        $notes = $request->request->get('notes');

        try {
            $this->astreinteManager->recordReplacement($astreinte, $notes);

            return new JsonResponse([
                'success' => true,
                'replacementCount' => $astreinte->getReplacementCount(),
                'message' => 'Remplacement enregistré'
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Export to CSV
     */
    #[Route('/export/{year}/{month}', name: 'admin_astreinte_export', methods: ['GET'])]
    public function export(int $year, int $month): Response
    {
        $astreintes = $this->astreinteRepository->findByMonth($year, $month);

        $csv = "Période,Début,Fin,Éducateur,Statut,Remplacements\n";
        foreach ($astreintes as $astreinte) {
            $csv .= sprintf(
                "%s,%s,%s,%s,%s,%d\n",
                $astreinte->getPeriodLabel() ?? '',
                $astreinte->getStartAt()->format('Y-m-d H:i'),
                $astreinte->getEndAt()->format('Y-m-d H:i'),
                $astreinte->getEducateur()?->getFullName() ?? 'Non affecté',
                $astreinte->getStatus(),
                $astreinte->getReplacementCount()
            );
        }

        $response = new Response($csv);
        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition',
            'attachment; filename="astreintes_' . $year . '_' . $month . '.csv"');

        return $response;
    }
}
