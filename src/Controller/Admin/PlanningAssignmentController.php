<?php

namespace App\Controller\Admin;

use App\Repository\SqueletteGardeRepository;
use App\Repository\UserRepository;
use App\Repository\VillaRepository;
use App\Service\AnnualDayCounterService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/planning-affectation')]
#[IsGranted('ROLE_ADMIN')]
class PlanningAssignmentController extends AbstractController
{
    public function __construct(
        private VillaRepository $villaRepository,
        private SqueletteGardeRepository $templateRepository,
        private UserRepository $userRepository,
        private AnnualDayCounterService $annualDayCounterService
    ) {
    }

    /**
     * Main assignment interface with FullCalendar
     */
    #[Route('', name: 'admin_planning_assignment_index', methods: ['GET'])]
    public function index(): Response
    {
        // Load initial context data
        $villas = $this->villaRepository->findAll();
        $templates = $this->templateRepository->findAll();
        $users = $this->userRepository->findAll();

        // Get current period (current month by default)
        $currentDate = new \DateTime();
        $currentYear = (int) $currentDate->format('Y');
        $currentMonth = (int) $currentDate->format('m');

        // Calculer les jours restants pour chaque utilisateur
        $usersRemainingDays = [];
        foreach ($users as $user) {
            $balanceDetails = $this->annualDayCounterService->getBalanceDetails($user, $currentYear);
            $usersRemainingDays[$user->getId()] = (int) $balanceDetails['jours_restants'];
        }

        return $this->render('admin/planning_assignment/index.html.twig', [
            'villas' => $villas,
            'templates' => $templates,
            'users' => $users,
            'usersRemainingDays' => $usersRemainingDays,
            'currentYear' => $currentYear,
            'currentMonth' => $currentMonth,
        ]);
    }
}
