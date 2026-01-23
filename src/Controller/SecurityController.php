<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    #[Route('/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        // Si l'utilisateur est déjà connecté, rediriger vers le dashboard
        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard');
        }

        // Récupère les erreurs de connexion s'il y en a
        $error = $authenticationUtils->getLastAuthenticationError();

        // Dernier nom d'utilisateur saisi
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout(): void
    {
        // Cette méthode peut rester vide - elle sera interceptée par le système de sécurité
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }

    #[Route('/force-password-change', name: 'app_force_password_change')]
    public function forcePasswordChange(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager
    ): Response {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        // If user doesn't need to change password, redirect to dashboard
        if (!$user->isForcePasswordChange()) {
            return $this->redirectToRoute('app_dashboard');
        }

        $errors = [];

        if ($request->isMethod('POST')) {
            $newPassword = $request->request->get('new_password');
            $confirmPassword = $request->request->get('confirm_password');

            // Validation
            if (empty($newPassword)) {
                $errors[] = 'Le nouveau mot de passe est obligatoire.';
            } elseif (strlen($newPassword) < 8) {
                $errors[] = 'Le mot de passe doit contenir au moins 8 caractères.';
            }

            if ($newPassword !== $confirmPassword) {
                $errors[] = 'Les mots de passe ne correspondent pas.';
            }

            if (empty($errors)) {
                // Update password
                $hashedPassword = $passwordHasher->hashPassword($user, $newPassword);
                $user->setPassword($hashedPassword);
                $user->setForcePasswordChange(false);

                $entityManager->flush();

                $this->addFlash('success', 'Votre mot de passe a été mis à jour avec succès.');

                return $this->redirectToRoute('app_dashboard');
            }
        }

        return $this->render('security/force_password_change.html.twig', [
            'errors' => $errors,
        ]);
    }
}
