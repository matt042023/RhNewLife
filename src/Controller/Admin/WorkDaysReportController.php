<?php

namespace App\Controller\Admin;

use App\Service\Affectation\JoursTravauxCalculator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/rapports/jours-travail')]
#[IsGranted('ROLE_ADMIN')]
class WorkDaysReportController extends AbstractController
{
    public function __construct(
        private JoursTravauxCalculator $calculator
    ) {}

    /**
     * Display the monthly work days report page
     */
    #[Route('', name: 'admin_work_days_report', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $currentYear = (int) ($request->query->get('year') ?? date('Y'));
        $currentMonth = (int) ($request->query->get('month') ?? date('n'));

        // Validate month
        if ($currentMonth < 1 || $currentMonth > 12) {
            $currentMonth = (int) date('n');
        }

        $report = $this->calculator->generateMonthlyReport($currentYear, $currentMonth);

        return $this->render('admin/work_days_report/index.html.twig', [
            'report' => $report,
            'currentYear' => $currentYear,
            'currentMonth' => $currentMonth,
        ]);
    }

    /**
     * API endpoint to get report data as JSON
     */
    #[Route('/api/{year}/{month}', name: 'api_work_days_report', methods: ['GET'])]
    public function apiReport(int $year, int $month): JsonResponse
    {
        if ($month < 1 || $month > 12) {
            return new JsonResponse(['error' => 'Invalid month'], 400);
        }

        $report = $this->calculator->generateMonthlyReport($year, $month);

        return new JsonResponse([
            'success' => true,
            'data' => $report
        ]);
    }

    /**
     * Export report as CSV
     */
    #[Route('/export/{year}/{month}.csv', name: 'admin_work_days_report_csv', methods: ['GET'])]
    public function exportCsv(int $year, int $month): Response
    {
        if ($month < 1 || $month > 12) {
            throw $this->createNotFoundException('Invalid month');
        }

        $report = $this->calculator->generateMonthlyReport($year, $month);

        // Generate CSV content
        $csv = [];
        $csv[] = ['Rapport des jours travaillés - ' . sprintf('%02d/%d', $month, $year)];
        $csv[] = [];
        $csv[] = ['Éducateur', 'Gardes Principales (jours)', 'Gardes Principales (heures)', 'Renforts (jours)', 'Renforts (heures)', 'Total (jours)', 'Total (heures)'];

        foreach ($report['users'] as $userData) {
            $csv[] = [
                $userData['fullName'],
                $userData['gardes_principales']['total_jours'],
                $userData['gardes_principales']['total_heures'],
                $userData['renforts']['total_jours'],
                $userData['renforts']['total_heures'],
                $userData['total']['jours'],
                $userData['total']['heures']
            ];
        }

        $csv[] = [];
        $csv[] = [
            'TOTAL',
            $report['totals']['gardes_principales_jours'],
            $report['totals']['gardes_principales_heures'],
            $report['totals']['renforts_jours'],
            $report['totals']['renforts_heures'],
            $report['totals']['total_jours'],
            $report['totals']['total_heures']
        ];

        // Convert to CSV string
        $output = fopen('php://temp', 'r+');
        foreach ($csv as $row) {
            fputcsv($output, $row, ';');
        }
        rewind($output);
        $csvContent = stream_get_contents($output);
        fclose($output);

        $response = new Response($csvContent);
        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', sprintf('attachment; filename="rapport_jours_travail_%02d_%d.csv"', $month, $year));

        return $response;
    }
}
