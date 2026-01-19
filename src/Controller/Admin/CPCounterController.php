<?php

namespace App\Controller\Admin;

use App\Entity\CompteurCP;
use App\Entity\User;
use App\Repository\CompteurCPRepository;
use App\Repository\UserRepository;
use App\Service\Payroll\CPCounterService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/compteurs-cp')]
#[IsGranted('ROLE_ADMIN')]
class CPCounterController extends AbstractController
{
    public function __construct(
        private CompteurCPRepository $compteurCPRepository,
        private UserRepository $userRepository,
        private CPCounterService $cpCounterService
    ) {
    }

    /**
     * Liste des compteurs CP de la période courante
     */
    #[Route('', name: 'admin_cp_counter_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $periode = $request->query->get('periode', CompteurCP::getCurrentPeriodeReference());

        $counters = $this->compteurCPRepository->findByPeriode($periode);

        // Trouver les utilisateurs sans compteur pour cette période
        $usersWithCounter = array_map(fn($c) => $c->getUser()?->getId(), $counters);
        $allUsers = $this->userRepository->findActiveEducators();
        $usersWithoutCounter = array_filter($allUsers, fn($u) => !in_array($u->getId(), $usersWithCounter, true));

        // Calculer les totaux
        $totals = [
            'solde_initial' => 0,
            'acquis' => 0,
            'pris' => 0,
            'ajustement' => 0,
            'solde_actuel' => 0,
        ];

        foreach ($counters as $counter) {
            $totals['solde_initial'] += (float) $counter->getSoldeInitial();
            $totals['acquis'] += (float) $counter->getAcquis();
            $totals['pris'] += (float) $counter->getPris();
            $totals['ajustement'] += (float) $counter->getAjustementAdmin();
            $totals['solde_actuel'] += $counter->getSoldeActuel();
        }

        // Périodes disponibles
        $periodes = $this->getAvailablePeriodes();

        return $this->render('admin/cp_counter/index.html.twig', [
            'counters' => $counters,
            'usersWithoutCounter' => $usersWithoutCounter,
            'periode' => $periode,
            'periode_label' => $this->getPeriodeLabel($periode),
            'periodes' => $periodes,
            'totals' => $totals,
        ]);
    }

    /**
     * Détail du compteur d'un utilisateur
     */
    #[Route('/utilisateur/{id}', name: 'admin_cp_counter_user', methods: ['GET'])]
    public function userDetail(User $user): Response
    {
        $counter = $this->cpCounterService->getOrCreateCounter($user);
        $allCounters = $this->compteurCPRepository->findBy(
            ['user' => $user],
            ['periodeReference' => 'DESC']
        );

        $balanceDetails = $this->cpCounterService->getBalanceDetails($user);

        // Récupérer les mouvements (historique simplifié basé sur les consolidations)
        $movements = $this->cpCounterService->getMovementsHistory($user);

        // Récupérer les absences CP prévues
        $pendingAbsences = $this->cpCounterService->getPendingCPAbsences($user);

        return $this->render('admin/cp_counter/user_detail.html.twig', [
            'user' => $user,
            'counter' => $counter,
            'allCounters' => $allCounters,
            'balanceDetails' => $balanceDetails,
            'movements' => $movements,
            'pendingAbsences' => $pendingAbsences,
        ]);
    }

    /**
     * Ajuster le solde d'un compteur
     */
    #[Route('/{id}/ajuster', name: 'admin_cp_counter_adjust', methods: ['POST'])]
    public function adjust(Request $request, CompteurCP $counter): Response
    {
        /** @var User $admin */
        $admin = $this->getUser();

        // Gestion du formulaire avec type (add/remove) et days
        $type = $request->request->get('type', 'add');
        $days = (float) $request->request->get('days', 0);
        $comment = $request->request->get('comment', '');

        // Calculer l'ajustement basé sur le type
        $adjustment = ($type === 'remove') ? -$days : $days;

        if ($adjustment === 0.0) {
            $this->addFlash('error', 'L\'ajustement ne peut pas être nul.');
            return $this->redirectToRoute('admin_cp_counter_user', ['id' => $counter->getUser()->getId()]);
        }

        if (empty($comment)) {
            $this->addFlash('error', 'Un commentaire est requis pour l\'ajustement.');
            return $this->redirectToRoute('admin_cp_counter_user', ['id' => $counter->getUser()->getId()]);
        }

        $this->cpCounterService->adjustBalance(
            $counter->getUser(),
            $adjustment,
            $comment,
            $admin
        );

        $this->addFlash('success', sprintf(
            'Ajustement de %s%.2f jours effectué.',
            $adjustment > 0 ? '+' : '',
            $adjustment
        ));

        return $this->redirectToRoute('admin_cp_counter_user', ['id' => $counter->getUser()->getId()]);
    }

    /**
     * Créer les compteurs pour les utilisateurs qui n'en ont pas
     */
    #[Route('/initialiser', name: 'admin_cp_counter_init', methods: ['POST'])]
    public function initializeCounters(): Response
    {
        $users = $this->compteurCPRepository->findUsersWithoutCurrentCounter();
        $count = 0;

        foreach ($users as $user) {
            $this->cpCounterService->getOrCreateCounter($user);
            $count++;
        }

        if ($count > 0) {
            $this->addFlash('success', sprintf('%d compteur(s) CP créé(s).', $count));
        } else {
            $this->addFlash('info', 'Tous les utilisateurs ont déjà un compteur CP.');
        }

        return $this->redirectToRoute('admin_cp_counter_index');
    }

    /**
     * Créer un compteur pour un utilisateur spécifique
     */
    #[Route('/creer/{id}', name: 'admin_cp_counter_create', methods: ['POST'])]
    public function createCounter(User $user): Response
    {
        $counter = $this->cpCounterService->getOrCreateCounter($user);

        $this->addFlash('success', sprintf(
            'Compteur CP créé pour %s.',
            $user->getFullName()
        ));

        return $this->redirectToRoute('admin_cp_counter_user', ['id' => $user->getId()]);
    }

    /**
     * Ajuster via AJAX (depuis une modale)
     */
    #[Route('/ajax/{id}/ajuster', name: 'admin_cp_counter_ajax_adjust', methods: ['POST'])]
    public function ajaxAdjust(Request $request, CompteurCP $counter): JsonResponse
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
            $this->cpCounterService->adjustBalance(
                $counter->getUser(),
                $adjustment,
                $comment,
                $admin
            );

            // Recharger le compteur
            $counter = $this->compteurCPRepository->find($counter->getId());

            return new JsonResponse([
                'success' => true,
                'counter' => [
                    'solde_initial' => (float) $counter->getSoldeInitial(),
                    'acquis' => (float) $counter->getAcquis(),
                    'pris' => (float) $counter->getPris(),
                    'ajustement' => (float) $counter->getAjustementAdmin(),
                    'solde_actuel' => $counter->getSoldeActuel(),
                ],
            ]);
        } catch (\Exception $e) {
            return new JsonResponse(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    /**
     * Export CSV des compteurs
     */
    #[Route('/export/{periode}.csv', name: 'admin_cp_counter_export', methods: ['GET'])]
    public function exportCsv(string $periode): Response
    {
        $counters = $this->compteurCPRepository->findByPeriode($periode);

        $csv = [];
        $csv[] = ['Période', $this->getPeriodeLabel($periode)];
        $csv[] = [];
        $csv[] = ['Matricule', 'Nom', 'Prénom', 'Solde initial', 'Acquis', 'Pris', 'Ajustement', 'Solde actuel'];

        foreach ($counters as $counter) {
            $user = $counter->getUser();
            $csv[] = [
                $user?->getMatricule() ?? 'N/A',
                $user?->getLastName(),
                $user?->getFirstName(),
                number_format((float) $counter->getSoldeInitial(), 2, ',', ''),
                number_format((float) $counter->getAcquis(), 2, ',', ''),
                number_format((float) $counter->getPris(), 2, ',', ''),
                number_format((float) $counter->getAjustementAdmin(), 2, ',', ''),
                number_format($counter->getSoldeActuel(), 2, ',', ''),
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
            'attachment; filename="compteurs_cp_%s.csv"',
            str_replace('-', '_', $periode)
        ));

        return $response;
    }

    /**
     * Retourne les périodes disponibles
     */
    private function getAvailablePeriodes(): array
    {
        $currentPeriode = CompteurCP::getCurrentPeriodeReference();
        $startYear = (int) substr($currentPeriode, 0, 4);

        $periodes = [];
        for ($i = 0; $i < 5; $i++) {
            $year = $startYear - $i;
            $periodes[] = sprintf('%d-%d', $year, $year + 1);
        }

        return $periodes;
    }

    /**
     * Retourne le libellé d'une période
     */
    private function getPeriodeLabel(string $periode): string
    {
        $years = explode('-', $periode);
        return sprintf('Juin %s - Mai %s', $years[0], $years[1]);
    }
}
