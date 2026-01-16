<?php

namespace App\Controller;

use App\Entity\Absence;
use App\Entity\User;
use App\Repository\AbsenceRepository;
use App\Service\Absence\AbsenceCounterService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class DashboardController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    #[Route('/dashboard', name: 'app_dashboard')]
    #[IsGranted('ROLE_USER')]
    public function index(#[CurrentUser] User $user): Response
    {
        // Si l'utilisateur est en onboarding, rediriger vers l'étape appropriée
        if ($user->getStatus() === User::STATUS_ONBOARDING) {
            // Vérifier s'il a déjà rempli les infos perso
            if (!$user->getPhone() || !$user->getAddress()) {
                return $this->redirectToRoute('app_onboarding_step1');
            }

            return $this->redirectToRoute('app_onboarding_step2');
        }

        // Rediriger vers le dashboard approprié selon le rôle
        if ($this->isGranted('ROLE_ADMIN')) {
            return $this->redirectToRoute('app_dashboard_admin');
        }

        if ($this->isGranted('ROLE_DIRECTOR')) {
            return $this->redirectToRoute('app_dashboard_director');
        }

        return $this->redirectToRoute('app_dashboard_employee');
    }

    #[Route('/dashboard/employee', name: 'app_dashboard_employee')]
    #[IsGranted('ROLE_USER')]
    public function employee(
        #[CurrentUser] User $user,
        AbsenceCounterService $counterService
    ): Response {
        // Get user's leave counters for current year
        $currentYear = (int) date('Y');
        $counters = $counterService->getUserCounters($user, $currentYear);

        // Current date for planning widget
        $now = new \DateTime();

        return $this->render('dashboard/employee.html.twig', [
            'user' => $user,
            'counters' => $counters,
            'currentYear' => (int) $now->format('Y'),
            'currentMonth' => (int) $now->format('m'),
        ]);
    }

    #[Route('/dashboard/director', name: 'app_dashboard_director')]
    #[IsGranted('ROLE_DIRECTOR')]
    public function director(#[CurrentUser] User $user): Response
    {
        return $this->render('dashboard/director.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/dashboard/admin', name: 'app_dashboard_admin')]
    #[IsGranted('ROLE_ADMIN')]
    public function admin(
        #[CurrentUser] User $user,
        AbsenceRepository $absenceRepository
    ): Response {
        // Get pending absences for admin dashboard
        $pendingAbsences = $absenceRepository->findBy([
            'status' => Absence::STATUS_PENDING
        ], ['createdAt' => 'DESC'], 10);

        // Current date for planning widget
        $now = new \DateTime();

        return $this->render('dashboard/admin.html.twig', [
            'user' => $user,
            'pendingAbsences' => $pendingAbsences,
            'currentYear' => (int) $now->format('Y'),
            'currentMonth' => (int) $now->format('m'),
        ]);
    }
}
