<?php

namespace App\Controller\Admin;

use App\Entity\ConsolidationPaie;
use App\Entity\User;
use App\Repository\ConsolidationPaieRepository;
use App\Repository\UserRepository;
use App\Service\Payroll\PayrollConsolidationService;
use App\Service\Payroll\PayrollExportService;
use App\Service\Payroll\PayrollHistoryService;
use App\Service\Payroll\PayrollNotificationService;
use App\Service\Payroll\PayrollValidationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/paie')]
#[IsGranted('ROLE_ADMIN')]
class PayrollController extends AbstractController
{
    public function __construct(
        private ConsolidationPaieRepository $consolidationRepository,
        private UserRepository $userRepository,
        private PayrollConsolidationService $consolidationService,
        private PayrollValidationService $validationService,
        private PayrollExportService $exportService,
        private PayrollHistoryService $historyService,
        private PayrollNotificationService $notificationService
    ) {
    }

    /**
     * Liste des périodes de paie disponibles
     */
    #[Route('', name: 'admin_payroll_index', methods: ['GET'])]
    public function index(): Response
    {
        $periods = $this->consolidationRepository->findAvailablePeriods();

        // Générer les 12 derniers mois (incluant le mois courant)
        $allPeriods = [];
        $currentDate = new \DateTime();
        for ($i = 0; $i < 12; $i++) {
            $period = $currentDate->format('Y-m');
            if (!in_array($period, $allPeriods, true)) {
                $allPeriods[] = $period;
            }
            $currentDate->modify('-1 month');
        }

        // Ajouter les périodes existantes qui ne sont pas dans les 12 derniers mois
        foreach ($periods as $period) {
            if (!in_array($period, $allPeriods, true)) {
                $allPeriods[] = $period;
            }
        }

        // Trier par ordre décroissant
        rsort($allPeriods);

        // Ajouter les stats pour chaque période
        $periodsData = [];
        foreach ($allPeriods as $period) {
            $stats = $this->validationService->getMonthStats($period);
            $periodsData[] = array_merge(['period' => $period], $stats);
        }

        $currentPeriod = date('Y-m');

        return $this->render('admin/payroll/index.html.twig', [
            'periods' => $periodsData,
            'currentPeriod' => $currentPeriod,
        ]);
    }

    /**
     * Vue mensuelle - tous les éducateurs
     */
    #[Route('/{period}', name: 'admin_payroll_month', methods: ['GET'], requirements: ['period' => '\d{4}-\d{2}'])]
    public function monthView(string $period): Response
    {
        $consolidations = $this->consolidationRepository->findByPeriod($period);
        $stats = $this->validationService->getMonthStats($period);

        return $this->render('admin/payroll/month_view.html.twig', [
            'period' => $period,
            'period_label' => $this->getPeriodLabel($period),
            'consolidations' => $consolidations,
            'stats' => $stats,
        ]);
    }

    /**
     * Consolider un mois
     */
    #[Route('/{period}/consolider', name: 'admin_payroll_consolidate', methods: ['POST'], requirements: ['period' => '\d{4}-\d{2}'])]
    public function consolidate(string $period): Response
    {
        /** @var User $admin */
        $admin = $this->getUser();

        $year = (int) substr($period, 0, 4);
        $month = (int) substr($period, 5, 2);

        $consolidations = $this->consolidationService->consolidateMonth($year, $month, $admin);

        $this->addFlash('success', sprintf(
            '%d rapports de paie ont été consolidés pour %s.',
            count($consolidations),
            $this->getPeriodLabel($period)
        ));

        return $this->redirectToRoute('admin_payroll_month', ['period' => $period]);
    }

    /**
     * Détail d'un rapport utilisateur
     */
    #[Route('/rapport/{id}', name: 'admin_payroll_show', methods: ['GET'])]
    public function show(ConsolidationPaie $consolidation): Response
    {
        $details = $this->consolidationService->getConsolidationDetails($consolidation);
        $history = $this->historyService->getHistory($consolidation);

        return $this->render('admin/payroll/user_detail.html.twig', [
            'consolidation' => $consolidation,
            'details' => $details,
            'history' => array_map(fn($h) => $this->historyService->formatHistoryEntry($h), $history),
        ]);
    }

    /**
     * Rafraîchir un rapport
     */
    #[Route('/rapport/{id}/rafraichir', name: 'admin_payroll_refresh', methods: ['POST'])]
    public function refresh(ConsolidationPaie $consolidation): Response
    {
        /** @var User $admin */
        $admin = $this->getUser();

        $this->consolidationService->refreshConsolidation($consolidation, $admin);

        $this->addFlash('success', 'Le rapport a été rafraîchi.');

        return $this->redirectToRoute('admin_payroll_show', ['id' => $consolidation->getId()]);
    }

    /**
     * Valider un rapport
     */
    #[Route('/rapport/{id}/valider', name: 'admin_payroll_validate', methods: ['POST'])]
    public function validate(ConsolidationPaie $consolidation): Response
    {
        /** @var User $admin */
        $admin = $this->getUser();

        $check = $this->validationService->canValidate($consolidation);
        if (!$check['valid']) {
            foreach ($check['errors'] as $error) {
                $this->addFlash('error', $error);
            }
            return $this->redirectToRoute('admin_payroll_show', ['id' => $consolidation->getId()]);
        }

        $this->validationService->validate($consolidation, $admin);

        // Envoyer la notification à l'éducateur
        $this->notificationService->notifyReportValidated($consolidation);

        $this->addFlash('success', 'Le rapport a été validé et est maintenant visible par l\'éducateur.');

        return $this->redirectToRoute('admin_payroll_show', ['id' => $consolidation->getId()]);
    }

    /**
     * Valider tous les rapports d'un mois
     */
    #[Route('/{period}/valider-tout', name: 'admin_payroll_validate_all', methods: ['POST'], requirements: ['period' => '\d{4}-\d{2}'])]
    public function validateAll(string $period): Response
    {
        /** @var User $admin */
        $admin = $this->getUser();

        $year = (int) substr($period, 0, 4);
        $month = (int) substr($period, 5, 2);

        $count = $this->validationService->validateMonth($year, $month, $admin);

        // Envoyer les notifications
        $consolidations = $this->consolidationRepository->findByPeriodAndStatus($period, ConsolidationPaie::STATUS_VALIDATED);
        foreach ($consolidations as $consolidation) {
            $this->notificationService->notifyReportValidated($consolidation);
        }

        $this->addFlash('success', sprintf('%d rapports ont été validés.', $count));

        return $this->redirectToRoute('admin_payroll_month', ['period' => $period]);
    }

    /**
     * Réouvrir un rapport
     */
    #[Route('/rapport/{id}/reouvrir', name: 'admin_payroll_reopen', methods: ['POST'])]
    public function reopen(Request $request, ConsolidationPaie $consolidation): Response
    {
        /** @var User $admin */
        $admin = $this->getUser();

        $reason = $request->request->get('reason', 'Correction nécessaire');

        $this->validationService->reopen($consolidation, $admin, $reason);

        $this->addFlash('success', 'Le rapport a été réouvert pour correction.');

        return $this->redirectToRoute('admin_payroll_show', ['id' => $consolidation->getId()]);
    }

    /**
     * Corriger un champ
     */
    #[Route('/rapport/{id}/corriger', name: 'admin_payroll_correct', methods: ['POST'])]
    public function correct(Request $request, ConsolidationPaie $consolidation): JsonResponse
    {
        /** @var User $admin */
        $admin = $this->getUser();

        $field = $request->request->get('field');
        $value = $request->request->get('value');
        $comment = $request->request->get('comment');

        try {
            $this->validationService->correctField($consolidation, $field, $value, $admin, $comment);

            // Notifier si le rapport était visible
            if (!$consolidation->isDraft() && $comment) {
                $this->notificationService->notifyReportCorrected($consolidation, $field, $comment);
            }

            return new JsonResponse(['success' => true]);
        } catch (\Exception $e) {
            return new JsonResponse(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    /**
     * Export CSV
     */
    #[Route('/{period}/export-csv', name: 'admin_payroll_export_csv', methods: ['GET'], requirements: ['period' => '\d{4}-\d{2}'])]
    public function exportCsv(string $period): Response
    {
        /** @var User $admin */
        $admin = $this->getUser();

        return $this->exportService->exportCSV($period, $admin);
    }

    /**
     * Export PDF
     */
    #[Route('/{period}/export-pdf', name: 'admin_payroll_export_pdf', methods: ['GET'], requirements: ['period' => '\d{4}-\d{2}'])]
    public function exportPdf(string $period): Response
    {
        /** @var User $admin */
        $admin = $this->getUser();

        return $this->exportService->exportPDF($period, $admin);
    }

    /**
     * Envoyer au comptable
     */
    #[Route('/{period}/envoyer-comptable', name: 'admin_payroll_send_accountant', methods: ['POST'], requirements: ['period' => '\d{4}-\d{2}'])]
    public function sendToAccountant(Request $request, string $period): Response
    {
        /** @var User $admin */
        $admin = $this->getUser();

        $accountantEmail = $request->request->get('email');

        if (!$accountantEmail || !filter_var($accountantEmail, FILTER_VALIDATE_EMAIL)) {
            $this->addFlash('error', 'Adresse email invalide.');
            return $this->redirectToRoute('admin_payroll_month', ['period' => $period]);
        }

        // Générer les exports
        $csvResponse = $this->exportService->exportCSV($period, $admin);
        ob_start();
        $csvResponse->sendContent();
        $csvContent = ob_get_clean();

        // Envoyer l'email
        $success = $this->notificationService->sendToAccountant(
            $period,
            $accountantEmail,
            $csvContent,
            null, // PDF optionnel
            $admin
        );

        if ($success) {
            // Marquer les rapports comme exportés et envoyés
            $this->exportService->markMonthAsExported($period, $admin);

            $consolidations = $this->consolidationRepository->findByPeriodAndStatus($period, ConsolidationPaie::STATUS_EXPORTED);
            foreach ($consolidations as $consolidation) {
                $this->validationService->markAsSentToAccountant($consolidation);
            }

            $this->addFlash('success', sprintf('L\'export a été envoyé à %s.', $accountantEmail));
        } else {
            $this->addFlash('error', 'Une erreur s\'est produite lors de l\'envoi.');
        }

        return $this->redirectToRoute('admin_payroll_month', ['period' => $period]);
    }

    /**
     * Historique d'un rapport (API)
     */
    #[Route('/rapport/{id}/historique', name: 'admin_payroll_history', methods: ['GET'])]
    public function history(ConsolidationPaie $consolidation): JsonResponse
    {
        $history = $this->historyService->getHistory($consolidation);
        $formatted = array_map(fn($h) => $this->historyService->formatHistoryEntry($h), $history);

        return new JsonResponse(['history' => $formatted]);
    }

    /**
     * Retourne le libellé d'une période
     */
    private function getPeriodLabel(string $period): string
    {
        $months = [
            '01' => 'Janvier', '02' => 'Février', '03' => 'Mars', '04' => 'Avril',
            '05' => 'Mai', '06' => 'Juin', '07' => 'Juillet', '08' => 'Août',
            '09' => 'Septembre', '10' => 'Octobre', '11' => 'Novembre', '12' => 'Décembre',
        ];

        $year = substr($period, 0, 4);
        $month = substr($period, 5, 2);

        return ($months[$month] ?? '') . ' ' . $year;
    }
}
