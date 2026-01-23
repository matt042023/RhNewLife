<?php

namespace App\Controller\Director;

use App\Entity\ConsolidationPaie;
use App\Entity\User;
use App\Repository\ConsolidationPaieRepository;
use App\Repository\UserRepository;
use App\Service\Payroll\CPCounterService;
use App\Service\Payroll\PayrollValidationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Contrôleur pour les vues paie des directeurs (consultation seule)
 */
#[Route('/directeur/paie')]
#[IsGranted('ROLE_DIRECTOR')]
class DirectorPayrollController extends AbstractController
{
    public function __construct(
        private ConsolidationPaieRepository $consolidationRepository,
        private UserRepository $userRepository,
        private PayrollValidationService $validationService,
        private CPCounterService $cpCounterService
    ) {
    }

    /**
     * Vue d'ensemble des rapports de paie
     */
    #[Route('', name: 'director_payroll_index')]
    public function index(Request $request): Response
    {
        // Période sélectionnée (par défaut : mois en cours)
        $now = new \DateTime();
        $selectedYear = $request->query->getInt('year', (int) $now->format('Y'));
        $selectedMonth = $request->query->getInt('month', (int) $now->format('n'));
        $period = sprintf('%04d-%02d', $selectedYear, $selectedMonth);

        // Statistiques du mois
        $stats = $this->validationService->getMonthStats($period);

        // Récupérer les consolidations du mois
        $consolidations = $this->consolidationRepository->findByPeriod($period);

        // Années disponibles
        $years = range((int) $now->format('Y'), (int) $now->format('Y') - 2);

        return $this->render('director/payroll/index.html.twig', [
            'consolidations' => $consolidations,
            'stats' => $stats,
            'selected_year' => $selectedYear,
            'selected_month' => $selectedMonth,
            'period' => $period,
            'years' => $years,
        ]);
    }

    /**
     * Détail d'un rapport de paie (lecture seule)
     */
    #[Route('/{id}', name: 'director_payroll_show', requirements: ['id' => '\d+'])]
    public function show(ConsolidationPaie $consolidation): Response
    {
        $user = $consolidation->getUser();

        // Récupérer les détails
        $absences = $consolidation->getJoursAbsence() ?? [];
        $elementsVariables = $consolidation->getElementsVariables();

        // Grouper les variables par catégorie
        $variablesByCategory = [];
        foreach ($elementsVariables as $variable) {
            $category = $variable->getCategory();
            if (!isset($variablesByCategory[$category])) {
                $variablesByCategory[$category] = [
                    'label' => $variable->getCategoryLabel(),
                    'items' => [],
                    'total' => 0,
                ];
            }
            $variablesByCategory[$category]['items'][] = $variable;
            $variablesByCategory[$category]['total'] += (float) $variable->getAmount();
        }

        return $this->render('director/payroll/show.html.twig', [
            'consolidation' => $consolidation,
            'user' => $user,
            'absences' => $absences,
            'variables_by_category' => $variablesByCategory,
        ]);
    }

    /**
     * Vue d'un éducateur spécifique
     */
    #[Route('/educateur/{id}', name: 'director_payroll_user', requirements: ['id' => '\d+'])]
    public function user(User $user, Request $request): Response
    {
        // Récupérer l'année sélectionnée
        $selectedYear = $request->query->getInt('year', (int) (new \DateTime())->format('Y'));

        // Récupérer les consolidations de l'utilisateur pour l'année
        $consolidations = $this->consolidationRepository->findByUserAndYear($user, $selectedYear);

        // Récupérer le compteur CP
        $cpCounter = $this->cpCounterService->getOrCreateCounter($user);

        // Années disponibles
        $years = range((int) (new \DateTime())->format('Y'), (int) (new \DateTime())->format('Y') - 2);

        return $this->render('director/payroll/user.html.twig', [
            'user' => $user,
            'consolidations' => $consolidations,
            'cp_counter' => $cpCounter,
            'selected_year' => $selectedYear,
            'years' => $years,
        ]);
    }

    /**
     * Vue des compteurs CP de tous les éducateurs
     */
    #[Route('/compteurs-cp', name: 'director_payroll_cp_counters')]
    public function cpCounters(): Response
    {
        // Récupérer tous les éducateurs actifs
        $users = $this->userRepository->findByRole('ROLE_EDUCATOR');

        // Récupérer les compteurs pour chaque utilisateur
        $counters = [];
        foreach ($users as $user) {
            $counters[] = [
                'user' => $user,
                'counter' => $this->cpCounterService->getOrCreateCounter($user),
            ];
        }

        // Trier par solde décroissant
        usort($counters, fn($a, $b) => $b['counter']->getSoldeActuel() <=> $a['counter']->getSoldeActuel());

        return $this->render('director/payroll/cp_counters.html.twig', [
            'counters' => $counters,
        ]);
    }
}
