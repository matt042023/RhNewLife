<?php

namespace App\Controller;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class DashboardController extends AbstractController
{
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

        return $this->render('dashboard/index.html.twig', [
            'user' => $user,
        ]);
    }
}
