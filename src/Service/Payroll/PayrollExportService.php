<?php

namespace App\Service\Payroll;

use App\Entity\ConsolidationPaie;
use App\Entity\ElementVariable;
use App\Entity\User;
use App\Repository\ConsolidationPaieRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Twig\Environment;

/**
 * Service d'export des données de paie (CSV et PDF)
 */
class PayrollExportService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ConsolidationPaieRepository $consolidationRepository,
        private PayrollValidationService $validationService,
        private Environment $twig,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Génère un export CSV pour un mois donné
     */
    public function exportCSV(string $period, User $admin): StreamedResponse
    {
        $consolidations = $this->consolidationRepository->findByPeriod($period);

        $response = new StreamedResponse(function () use ($consolidations) {
            $handle = fopen('php://output', 'w');

            // BOM UTF-8 pour Excel
            fwrite($handle, "\xEF\xBB\xBF");

            // En-tête CSV avec séparateur point-virgule
            $headers = [
                'Matricule',
                'Nom',
                'Prenom',
                'Jours travailles',
                'CP pris',
                'Maladie',
                'Autres absences',
                'Prime',
                'Avance',
                'Frais',
                'Retenue',
                'Total variables',
            ];
            fputcsv($handle, $headers, ';');

            // Données
            foreach ($consolidations as $consolidation) {
                $user = $consolidation->getUser();
                if (!$user) {
                    continue;
                }

                // Calcul des totaux par catégorie de variables
                $variablesByCategory = $this->calculateVariablesByCategory($consolidation);

                // Calcul des absences par type
                $absences = $consolidation->getJoursAbsence() ?? [];
                $maladie = $absences['MAL'] ?? $absences['MALADIE'] ?? 0;
                $autresAbsences = $consolidation->getTotalJoursAbsence() - $maladie - ($absences['CP'] ?? 0);

                $row = [
                    $user->getMatricule() ?? 'N/A',
                    $user->getLastName(),
                    $user->getFirstName(),
                    number_format($consolidation->getTotalJoursTravailes(), 2, ',', ''),
                    number_format((float) $consolidation->getCpPris(), 2, ',', ''),
                    number_format($maladie, 2, ',', ''),
                    number_format(max(0, $autresAbsences), 2, ',', ''),
                    number_format($variablesByCategory['prime'] + $variablesByCategory['prime_exceptionnelle'], 2, ',', ''),
                    number_format($variablesByCategory['avance'] + $variablesByCategory['acompte'], 2, ',', ''),
                    number_format($variablesByCategory['frais'] + $variablesByCategory['indemnite_transport'], 2, ',', ''),
                    number_format($variablesByCategory['retenue'], 2, ',', ''),
                    number_format((float) $consolidation->getTotalVariables(), 2, ',', ''),
                ];

                fputcsv($handle, $row, ';');
            }

            fclose($handle);
        });

        $filename = sprintf('export_paie_%s.csv', str_replace('-', '_', $period));
        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', sprintf('attachment; filename="%s"', $filename));

        $this->logger->info('Export CSV généré', [
            'period' => $period,
            'count' => count($consolidations),
        ]);

        return $response;
    }

    /**
     * Génère un export PDF pour un mois donné
     */
    public function exportPDF(string $period, User $admin): Response
    {
        $consolidations = $this->consolidationRepository->findByPeriod($period);

        // Préparer les données pour le template
        $data = $this->preparePDFData($consolidations, $period);

        // Générer le HTML
        $html = $this->twig->render('admin/payroll/export_pdf.html.twig', [
            'period' => $period,
            'period_label' => $this->getPeriodLabel($period),
            'data' => $data,
            'generated_at' => new \DateTime(),
            'generated_by' => $admin,
        ]);

        // Pour l'instant, on retourne le HTML
        // L'intégration avec un générateur PDF (dompdf, wkhtmltopdf, etc.) sera faite ultérieurement
        $response = new Response($html);
        $response->headers->set('Content-Type', 'text/html; charset=utf-8');

        $this->logger->info('Export PDF généré', [
            'period' => $period,
            'count' => count($consolidations),
        ]);

        return $response;
    }

    /**
     * Génère un rapport individuel pour un utilisateur
     */
    public function exportUserReport(ConsolidationPaie $consolidation, User $admin): Response
    {
        $user = $consolidation->getUser();

        // Préparer les données détaillées
        $data = [
            'consolidation' => $consolidation,
            'user' => $user,
            'period_label' => $consolidation->getPeriodLabel(),
            'affectations' => $this->getAffectationsForReport($consolidation),
            'events' => $this->getEventsForReport($consolidation),
            'absences' => $this->getAbsencesForReport($consolidation),
            'variables' => $this->getVariablesForReport($consolidation),
            'cp' => [
                'solde_debut' => (float) $consolidation->getCpSoldeDebut(),
                'acquis' => (float) $consolidation->getCpAcquis(),
                'pris' => (float) $consolidation->getCpPris(),
                'solde_fin' => (float) $consolidation->getCpSoldeFin(),
            ],
            'totals' => [
                'jours_travailes' => (float) $consolidation->getJoursTravailes(),
                'jours_evenements' => (float) $consolidation->getJoursEvenements(),
                'total_jours' => $consolidation->getTotalJoursTravailes(),
                'total_absences' => $consolidation->getTotalJoursAbsence(),
                'total_variables' => (float) $consolidation->getTotalVariables(),
            ],
        ];

        $html = $this->twig->render('admin/payroll/user_report_pdf.html.twig', [
            'data' => $data,
            'generated_at' => new \DateTime(),
        ]);

        $response = new Response($html);
        $response->headers->set('Content-Type', 'text/html; charset=utf-8');

        return $response;
    }

    /**
     * Prépare les données pour l'export PDF mensuel
     */
    private function preparePDFData(array $consolidations, string $period): array
    {
        $rows = [];
        $totals = [
            'jours_travailes' => 0,
            'cp_pris' => 0,
            'maladie' => 0,
            'autres_absences' => 0,
            'primes' => 0,
            'avances' => 0,
            'frais' => 0,
            'retenues' => 0,
            'total_variables' => 0,
        ];

        foreach ($consolidations as $consolidation) {
            $user = $consolidation->getUser();
            if (!$user) {
                continue;
            }

            $variablesByCategory = $this->calculateVariablesByCategory($consolidation);
            $absences = $consolidation->getJoursAbsence() ?? [];
            $maladie = $absences['MAL'] ?? $absences['MALADIE'] ?? 0;
            $cpPris = (float) $consolidation->getCpPris();
            $autresAbsences = max(0, $consolidation->getTotalJoursAbsence() - $maladie - ($absences['CP'] ?? 0));

            $primes = $variablesByCategory['prime'] + $variablesByCategory['prime_exceptionnelle'];
            $avances = $variablesByCategory['avance'] + $variablesByCategory['acompte'];
            $frais = $variablesByCategory['frais'] + $variablesByCategory['indemnite_transport'];
            $retenues = $variablesByCategory['retenue'];

            $row = [
                'user' => $user,
                'matricule' => $user->getMatricule() ?? 'N/A',
                'nom' => $user->getLastName(),
                'prenom' => $user->getFirstName(),
                'jours_travailes' => $consolidation->getTotalJoursTravailes(),
                'cp_pris' => $cpPris,
                'maladie' => $maladie,
                'autres_absences' => $autresAbsences,
                'primes' => $primes,
                'avances' => $avances,
                'frais' => $frais,
                'retenues' => $retenues,
                'total_variables' => (float) $consolidation->getTotalVariables(),
                'status' => $consolidation->getStatus(),
                'status_label' => $consolidation->getStatusLabel(),
            ];

            $rows[] = $row;

            // Accumuler les totaux
            $totals['jours_travailes'] += $consolidation->getTotalJoursTravailes();
            $totals['cp_pris'] += $cpPris;
            $totals['maladie'] += $maladie;
            $totals['autres_absences'] += $autresAbsences;
            $totals['primes'] += $primes;
            $totals['avances'] += $avances;
            $totals['frais'] += $frais;
            $totals['retenues'] += $retenues;
            $totals['total_variables'] += (float) $consolidation->getTotalVariables();
        }

        return [
            'rows' => $rows,
            'totals' => $totals,
            'count' => count($rows),
        ];
    }

    /**
     * Calcule les variables par catégorie pour une consolidation
     *
     * @return array<string, float>
     */
    private function calculateVariablesByCategory(ConsolidationPaie $consolidation): array
    {
        $categories = [
            'prime' => 0,
            'prime_exceptionnelle' => 0,
            'avance' => 0,
            'acompte' => 0,
            'frais' => 0,
            'indemnite_transport' => 0,
            'retenue' => 0,
        ];

        foreach ($consolidation->getElementsVariables() as $variable) {
            $category = $variable->getCategory();
            if (isset($categories[$category])) {
                $categories[$category] += (float) $variable->getAmount();
            }
        }

        return $categories;
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

    /**
     * Prépare les affectations pour le rapport utilisateur
     */
    private function getAffectationsForReport(ConsolidationPaie $consolidation): array
    {
        // Cette méthode serait similaire à celle de PayrollConsolidationService
        // Pour l'instant, retourne un tableau vide - sera complété avec le contrôleur
        return [];
    }

    /**
     * Prépare les événements pour le rapport utilisateur
     */
    private function getEventsForReport(ConsolidationPaie $consolidation): array
    {
        return [];
    }

    /**
     * Prépare les absences pour le rapport utilisateur
     */
    private function getAbsencesForReport(ConsolidationPaie $consolidation): array
    {
        $absences = $consolidation->getJoursAbsence() ?? [];
        $result = [];

        $labels = [
            'CP' => 'Congés payés',
            'MAL' => 'Maladie',
            'MALADIE' => 'Maladie',
            'AT' => 'Accident du travail',
            'MATERNITE' => 'Maternité',
            'PATERNITE' => 'Paternité',
            'FORMATION' => 'Formation',
            'SANS_SOLDE' => 'Sans solde',
            'OTHER' => 'Autres',
        ];

        foreach ($absences as $code => $days) {
            if ($days > 0) {
                $result[] = [
                    'code' => $code,
                    'label' => $labels[$code] ?? $code,
                    'days' => $days,
                ];
            }
        }

        return $result;
    }

    /**
     * Prépare les variables pour le rapport utilisateur
     */
    private function getVariablesForReport(ConsolidationPaie $consolidation): array
    {
        $variables = [];

        foreach ($consolidation->getElementsVariables() as $variable) {
            $variables[] = [
                'category' => $variable->getCategory(),
                'category_label' => $variable->getCategoryLabel(),
                'label' => $variable->getLabel(),
                'amount' => (float) $variable->getAmount(),
                'description' => $variable->getDescription(),
            ];
        }

        return $variables;
    }

    /**
     * Génère le contenu de l'email pour le comptable
     */
    public function generateAccountantEmailContent(string $period): array
    {
        $stats = $this->validationService->getMonthStats($period);
        $periodLabel = $this->getPeriodLabel($period);

        $subject = sprintf('Export paie %s - RhNewLife', $periodLabel);

        $body = sprintf(
            "Bonjour,\n\n" .
            "Veuillez trouver ci-joints les documents de paie pour %s.\n\n" .
            "Récapitulatif :\n" .
            "- Nombre d'éducateurs : %d\n" .
            "- Rapports validés : %d\n\n" .
            "Cordialement,\n" .
            "L'équipe RhNewLife",
            $periodLabel,
            $stats['total'],
            $stats['validated']
        );

        return [
            'subject' => $subject,
            'body' => $body,
            'period' => $period,
            'period_label' => $periodLabel,
            'stats' => $stats,
        ];
    }

    /**
     * Marque toutes les consolidations d'un mois comme exportées
     */
    public function markMonthAsExported(string $period, User $admin): int
    {
        $consolidations = $this->consolidationRepository->findByPeriodAndStatus(
            $period,
            ConsolidationPaie::STATUS_VALIDATED
        );

        $count = 0;
        foreach ($consolidations as $consolidation) {
            $this->validationService->markAsExported($consolidation, $admin);
            $count++;
        }

        $this->logger->info('Mois marqué comme exporté', [
            'period' => $period,
            'count' => $count,
        ]);

        return $count;
    }
}
