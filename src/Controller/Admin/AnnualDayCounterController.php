<?php

namespace App\Controller\Admin;

use App\Entity\CompteurJoursAnnuels;
use App\Entity\User;
use App\Repository\CompteurJoursAnnuelsRepository;
use App\Repository\UserRepository;
use App\Service\AnnualDayCounterService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/compteurs-jours-annuels')]
#[IsGranted('ROLE_ADMIN')]
class AnnualDayCounterController extends AbstractController
{
    public function __construct(
        private CompteurJoursAnnuelsRepository $compteurRepository,
        private UserRepository $userRepository,
        private AnnualDayCounterService $annualDayCounterService
    ) {
    }

    /**
     * Liste des compteurs jours annuels pour une année
     */
    #[Route('', name: 'admin_annual_day_counter_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $currentYear = CompteurJoursAnnuels::getCurrentYear();
        $year = $request->query->getInt('year', $currentYear);

        $counters = $this->compteurRepository->findByYearWithUsers($year);

        // Trouver les utilisateurs sans compteur pour cette année
        $usersWithCounter = array_map(fn($c) => $c->getUser()?->getId(), $counters);
        $allUsers = $this->userRepository->findActiveEducators();
        $usersWithoutCounter = array_filter($allUsers, fn($u) => !in_array($u->getId(), $usersWithCounter, true));

        // Récupérer les compteurs avec calculs dynamiques
        $countersWithData = $this->annualDayCounterService->getCountersWithDynamicData($counters);

        // Calculer les totaux avec les valeurs dynamiques
        $totals = [
            'jours_alloues' => 0,
            'jours_consommes' => 0,
            'ajustement' => 0,
            'jours_restants' => 0,
        ];

        foreach ($countersWithData as $item) {
            $counter = $item['counter'];
            $totals['jours_alloues'] += (float) $counter->getJoursAlloues();
            $totals['jours_consommes'] += $item['jours_consommes'];
            $totals['ajustement'] += (float) $counter->getAjustementAdmin();
            $totals['jours_restants'] += $item['jours_restants'];
        }

        // Années disponibles (5 dernières années)
        $years = [];
        for ($i = 0; $i < 5; $i++) {
            $years[] = $currentYear - $i;
        }

        return $this->render('admin/annual_day_counter/index.html.twig', [
            'countersWithData' => $countersWithData,
            'usersWithoutCounter' => $usersWithoutCounter,
            'year' => $year,
            'years' => $years,
            'totals' => $totals,
        ]);
    }

    /**
     * Détail du compteur d'un utilisateur
     */
    #[Route('/utilisateur/{id}', name: 'admin_annual_day_counter_user', methods: ['GET'])]
    public function userDetail(User $user, Request $request): Response
    {
        $currentYear = CompteurJoursAnnuels::getCurrentYear();
        $year = $request->query->getInt('year', $currentYear);

        $counter = $this->annualDayCounterService->getOrCreateCounter($user, $year);
        $allCounters = $this->compteurRepository->findAllByUser($user);
        $balanceDetails = $this->annualDayCounterService->getBalanceDetails($user, $year);
        $movements = $this->annualDayCounterService->getMovementsHistory($user, $year);

        // Années disponibles pour le sélecteur
        $years = [];
        $currentYearValue = CompteurJoursAnnuels::getCurrentYear();
        for ($i = 0; $i < 5; $i++) {
            $years[] = $currentYearValue - $i;
        }

        return $this->render('admin/annual_day_counter/user_detail.html.twig', [
            'user' => $user,
            'counter' => $counter,
            'allCounters' => $allCounters,
            'balanceDetails' => $balanceDetails,
            'movements' => $movements,
            'year' => $year,
            'years' => $years,
        ]);
    }

    /**
     * Ajuster le solde d'un compteur (formulaire)
     */
    #[Route('/{id}/ajuster', name: 'admin_annual_day_counter_adjust', methods: ['POST'])]
    public function adjust(Request $request, CompteurJoursAnnuels $counter): Response
    {
        /** @var User $admin */
        $admin = $this->getUser();

        $type = $request->request->get('type', 'add');
        $days = (float) $request->request->get('days', 0);
        $comment = $request->request->get('comment', '');

        $adjustment = ($type === 'remove') ? -$days : $days;

        if ($adjustment === 0.0) {
            $this->addFlash('error', 'L\'ajustement ne peut pas être nul.');
            return $this->redirectToRoute('admin_annual_day_counter_user', ['id' => $counter->getUser()->getId()]);
        }

        if (empty($comment)) {
            $this->addFlash('error', 'Un commentaire est requis pour l\'ajustement.');
            return $this->redirectToRoute('admin_annual_day_counter_user', ['id' => $counter->getUser()->getId()]);
        }

        $this->annualDayCounterService->adjustBalance(
            $counter->getUser(),
            $adjustment,
            $comment,
            $admin,
            $counter->getYear()
        );

        $this->addFlash('success', sprintf(
            'Ajustement de %s%.0f jours effectué.',
            $adjustment > 0 ? '+' : '',
            $adjustment
        ));

        return $this->redirectToRoute('admin_annual_day_counter_user', ['id' => $counter->getUser()->getId()]);
    }

    /**
     * Ajuster via AJAX (depuis une modale)
     */
    #[Route('/ajax/{id}/ajuster', name: 'admin_annual_day_counter_ajax_adjust', methods: ['POST'])]
    public function ajaxAdjust(Request $request, CompteurJoursAnnuels $counter): JsonResponse
    {
        /** @var User $admin */
        $admin = $this->getUser();

        $data = json_decode($request->getContent(), true);
        $adjustment = (float) ($data['adjustment'] ?? 0);
        $comment = $data['comment'] ?? '';

        if ($adjustment === 0.0) {
            return new JsonResponse(['success' => false, 'error' => 'L\'ajustement ne peut pas être nul.'], 400);
        }

        if (empty($comment)) {
            return new JsonResponse(['success' => false, 'error' => 'Un commentaire est requis.'], 400);
        }

        try {
            $this->annualDayCounterService->adjustBalance(
                $counter->getUser(),
                $adjustment,
                $comment,
                $admin,
                $counter->getYear()
            );

            // Recharger le compteur
            $counter = $this->compteurRepository->find($counter->getId());

            return new JsonResponse([
                'success' => true,
                'counter' => [
                    'jours_alloues' => (float) $counter->getJoursAlloues(),
                    'jours_consommes' => (float) $counter->getJoursConsommes(),
                    'ajustement' => (float) $counter->getAjustementAdmin(),
                    'jours_restants' => $counter->getJoursRestants(),
                ],
            ]);
        } catch (\Exception $e) {
            return new JsonResponse(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    /**
     * Créer les compteurs pour les utilisateurs qui n'en ont pas
     */
    #[Route('/initialiser', name: 'admin_annual_day_counter_init', methods: ['POST'])]
    public function initializeCounters(Request $request): Response
    {
        $year = $request->request->getInt('year', CompteurJoursAnnuels::getCurrentYear());
        $users = $this->compteurRepository->findUsersWithoutCounter($year);
        $count = 0;

        foreach ($users as $user) {
            $this->annualDayCounterService->getOrCreateCounter($user, $year);
            $count++;
        }

        if ($count > 0) {
            $this->addFlash('success', sprintf('%d compteur(s) jours annuels créé(s).', $count));
        } else {
            $this->addFlash('info', 'Tous les utilisateurs ont déjà un compteur jours annuels.');
        }

        return $this->redirectToRoute('admin_annual_day_counter_index', ['year' => $year]);
    }

    /**
     * Créer un compteur pour un utilisateur spécifique
     */
    #[Route('/creer/{id}', name: 'admin_annual_day_counter_create', methods: ['POST'])]
    public function createCounter(User $user, Request $request): Response
    {
        $year = $request->request->getInt('year', CompteurJoursAnnuels::getCurrentYear());
        $counter = $this->annualDayCounterService->getOrCreateCounter($user, $year);

        $this->addFlash('success', sprintf(
            'Compteur jours annuels créé pour %s.',
            $user->getFullName()
        ));

        return $this->redirectToRoute('admin_annual_day_counter_user', ['id' => $user->getId()]);
    }

    /**
     * Export CSV des compteurs
     */
    #[Route('/export/{year}.csv', name: 'admin_annual_day_counter_export', methods: ['GET'])]
    public function exportCsv(int $year): Response
    {
        $counters = $this->compteurRepository->findByYearWithUsers($year);

        $csv = [];
        $csv[] = ['Année', $year];
        $csv[] = [];
        $csv[] = ['Matricule', 'Nom', 'Prénom', 'Jours Alloués', 'Jours Consommés', 'Ajustement', 'Jours Restants', '% Utilisé'];

        foreach ($counters as $counter) {
            $user = $counter->getUser();
            $csv[] = [
                $user?->getMatricule() ?? 'N/A',
                $user?->getLastName(),
                $user?->getFirstName(),
                number_format((float) $counter->getJoursAlloues(), 0, ',', ''),
                number_format((float) $counter->getJoursConsommes(), 0, ',', ''),
                number_format((float) $counter->getAjustementAdmin(), 0, ',', ''),
                number_format($counter->getJoursRestants(), 0, ',', ''),
                number_format($counter->getPercentageUsed(), 1, ',', '') . '%',
            ];
        }

        $output = fopen('php://temp', 'r+');
        fwrite($output, "\xEF\xBB\xBF"); // BOM UTF-8
        foreach ($csv as $row) {
            fputcsv($output, $row, ';');
        }
        rewind($output);
        $csvContent = stream_get_contents($output);
        fclose($output);

        $response = new Response($csvContent);
        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', sprintf(
            'attachment; filename="compteurs_jours_annuels_%d.csv"',
            $year
        ));

        return $response;
    }
}
