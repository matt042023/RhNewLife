<?php

namespace App\Controller;

use App\Entity\ConsolidationPaie;
use App\Repository\ConsolidationPaieRepository;
use App\Repository\DocumentRepository;
use App\Service\Payroll\CPCounterService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Contrôleur pour les vues paie des éducateurs (espace personnel)
 */
#[Route('/mon-espace')]
#[IsGranted('ROLE_USER')]
class MyPayrollController extends AbstractController
{
    public function __construct(
        private ConsolidationPaieRepository $consolidationRepository,
        private DocumentRepository $documentRepository,
        private CPCounterService $cpCounterService
    ) {
    }

    /**
     * Liste des rapports de paie de l'utilisateur connecté
     */
    #[Route('/paie', name: 'my_payroll_index')]
    public function index(): Response
    {
        $user = $this->getUser();

        // Récupérer les consolidations validées de l'utilisateur (triées par period DESC)
        $consolidations = $this->consolidationRepository->findBy(
            ['user' => $user],
            ['period' => 'DESC']
        );

        // Filtrer pour ne montrer que les rapports validés
        $validatedConsolidations = array_filter(
            $consolidations,
            fn($c) => $c->isValidated() || $c->isExported()
        );

        // Récupérer le compteur CP actuel
        $cpCounter = $this->cpCounterService->getOrCreateCounter($user);

        // Grouper par année
        $byYear = [];
        foreach ($validatedConsolidations as $consolidation) {
            $year = $consolidation->getYear();
            if (!isset($byYear[$year])) {
                $byYear[$year] = [];
            }
            $byYear[$year][] = $consolidation;
        }

        return $this->render('my_payroll/index.html.twig', [
            'consolidations_by_year' => $byYear,
            'cp_counter' => $cpCounter,
        ]);
    }

    /**
     * Détail d'un rapport de paie
     */
    #[Route('/paie/{id}', name: 'my_payroll_show', requirements: ['id' => '\d+'])]
    public function show(ConsolidationPaie $consolidation): Response
    {
        $user = $this->getUser();

        // Vérifier que le rapport appartient à l'utilisateur
        if ($consolidation->getUser() !== $user) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas accéder à ce rapport.');
        }

        // Vérifier que le rapport est validé
        if ($consolidation->isDraft()) {
            throw $this->createAccessDeniedException('Ce rapport n\'est pas encore disponible.');
        }

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

        return $this->render('my_payroll/show.html.twig', [
            'consolidation' => $consolidation,
            'absences' => $absences,
            'variables_by_category' => $variablesByCategory,
        ]);
    }

    /**
     * Liste des fiches de paie de l'utilisateur
     */
    #[Route('/fiches-paie', name: 'my_payroll_payslips')]
    public function payslips(): Response
    {
        $user = $this->getUser();

        // Récupérer les fiches de paie (documents de type fiche_paie)
        $payslips = $this->documentRepository->findBy(
            ['user' => $user, 'type' => 'fiche_paie'],
            ['uploadedAt' => 'DESC']
        );

        // Grouper par année
        $byYear = [];
        foreach ($payslips as $payslip) {
            $year = $payslip->getUploadedAt()->format('Y');
            if (!isset($byYear[$year])) {
                $byYear[$year] = [];
            }
            $byYear[$year][] = $payslip;
        }

        return $this->render('my_payroll/payslips.html.twig', [
            'payslips_by_year' => $byYear,
        ]);
    }

    /**
     * Affiche le solde de congés payés
     */
    #[Route('/conges', name: 'my_payroll_conges')]
    public function conges(): Response
    {
        $user = $this->getUser();

        // Récupérer le compteur CP
        $cpCounter = $this->cpCounterService->getOrCreateCounter($user);

        // Récupérer l'historique des mouvements (simplifié - les derniers rapports validés)
        $consolidations = $this->consolidationRepository->findBy(
            ['user' => $user],
            ['period' => 'DESC'],
            12
        );

        $validatedConsolidations = array_filter(
            $consolidations,
            fn($c) => !$c->isDraft()
        );

        return $this->render('my_payroll/conges.html.twig', [
            'cp_counter' => $cpCounter,
            'consolidations' => $validatedConsolidations,
        ]);
    }
}
