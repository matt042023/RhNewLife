<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\InvitationManager;
use App\Service\OnboardingManager;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/onboarding')]
class OnboardingController extends AbstractController
{
    public function __construct(
        private InvitationManager $invitationManager,
        private OnboardingManager $onboardingManager,
        private Security $security,
        private LoggerInterface $logger
    ) {}

    /**
     * Page d'activation du compte via le token d'invitation
     */
    #[Route('/activate/{token}', name: 'app_onboarding_activate', methods: ['GET', 'POST'])]
    public function activate(string $token, Request $request): Response
    {
        // Valide le token
        $invitation = $this->invitationManager->validateToken($token);

        if (!$invitation) {
            return $this->render('onboarding/expired.html.twig', [
                'token' => $token,
            ]);
        }

        // Traitement du formulaire
        if ($request->isMethod('POST')) {
            $password = $request->request->get('password');
            $passwordConfirm = $request->request->get('password_confirm');
            $acceptCGU = $request->request->get('accept_cgu', false);

            $errors = [];

            // Validation
            if ($password !== $passwordConfirm) {
                $errors[] = 'Les mots de passe ne correspondent pas.';
            }

            if (!$acceptCGU) {
                $errors[] = 'Vous devez accepter les CGU pour continuer.';
            }

            try {
                if (empty($errors)) {
                    // Activation du compte
                    $user = $this->onboardingManager->activateAccount(
                        $invitation,
                        $password,
                        (bool) $acceptCGU
                    );

                    // Connexion automatique de l'utilisateur
                    $this->security->login($user);

                    // Message de bienvenue et redirection vers étape 1
                    $this->addFlash('success', 'Votre compte a été activé avec succès ! Bienvenue, ' . $user->getFirstName() . ' !');

                    return $this->redirectToRoute('app_onboarding_step1');
                }
            } catch (\InvalidArgumentException $e) {
                $errors[] = $e->getMessage();
            } catch (\Exception $e) {
                // Logger l'erreur complète pour le débogage
                $this->logger->error('Erreur lors de l\'activation du compte', [
                    'token' => $token,
                    'exception' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                $errors[] = 'Une erreur est survenue. Veuillez réessayer. Détails: ' . $e->getMessage();
            }

            // S'il y a des erreurs, les stocker en flash et rediriger (requis par Turbo)
            if (!empty($errors)) {
                foreach ($errors as $error) {
                    $this->addFlash('error', $error);
                }
                return $this->redirectToRoute('app_onboarding_activate', ['token' => $token]);
            }
        }

        // Récupérer les erreurs depuis les flash messages
        $errors = [];
        foreach ($this->container->get('request_stack')->getSession()->getFlashBag()->get('error', []) as $error) {
            $errors[] = $error;
        }

        return $this->render('onboarding/activate.html.twig', [
            'invitation' => $invitation,
            'errors' => $errors,
        ]);
    }

    /**
     * Étape 1 : Informations personnelles
     */
    #[Route('/step1', name: 'app_onboarding_step1', methods: ['GET', 'POST'])]
    public function step1(Request $request, #[CurrentUser] User $user): Response
    {
        // Vérifier que l'utilisateur est en statut onboarding
        if ($user->getStatus() !== User::STATUS_ONBOARDING) {
            return $this->redirectToRoute('app_dashboard');
        }

        $errors = [];
        $formData = [];

        if ($request->isMethod('POST')) {
            $formData = [
                'phone' => $request->request->get('phone'),
                'address' => $request->request->get('address'),
                'family_status' => $request->request->get('family_status'),
                'children' => $request->request->get('children'),
                'iban' => $request->request->get('iban'),
                'bic' => $request->request->get('bic'),
                'mutuelle_enabled' => $request->request->get('mutuelle_enabled') === '1',
                'prevoyance_enabled' => $request->request->get('prevoyance_enabled') === '1',
            ];

            try {
                $this->onboardingManager->updateProfile($user, [
                    'phone' => $formData['phone'],
                    'address' => $formData['address'],
                    'familyStatus' => $formData['family_status'],
                    'children' => $formData['children'],
                    'iban' => $formData['iban'],
                    'bic' => $formData['bic'],
                    'mutuelleEnabled' => $formData['mutuelle_enabled'],
                    'prevoyanceEnabled' => $formData['prevoyance_enabled'],
                ]);
                $this->addFlash('success', 'Vos informations ont été enregistrées.');

                return $this->redirectToRoute('app_onboarding_step2');
            } catch (\Exception $e) {
                $errors[] = $e->getMessage();
            }
        }

        return $this->render('onboarding/step1.html.twig', [
            'user' => $user,
            'errors' => $errors,
            'formData' => $formData,
        ]);
    }

    /**
     * Étape 2 : Téléversement des justificatifs
     */
    #[Route('/step2', name: 'app_onboarding_step2', methods: ['GET'])]
    public function step2(#[CurrentUser] User $user): Response
    {
        // Vérifier que l'utilisateur est en statut onboarding
        if ($user->getStatus() !== User::STATUS_ONBOARDING) {
            return $this->redirectToRoute('app_dashboard');
        }

        // Si le dossier est déjà soumis, rediriger vers la page de confirmation
        if ($user->isSubmitted()) {
            $this->addFlash('info', 'Votre dossier a déjà été soumis et est en attente de validation.');
            return $this->redirectToRoute('app_onboarding_completed');
        }

        // Récupérer les documents par type
        $documents = [
            'cni' => null,
            'rib' => null,
            'domicile' => null,
            'honorabilite' => null,
        ];

        foreach ($user->getDocuments() as $doc) {
            $documents[$doc->getType()] = $doc;
        }

        // Calculer le statut de complétion
        $uploadedCount = 0;
        foreach ($documents as $doc) {
            if ($doc !== null) {
                $uploadedCount++;
            }
        }

        $requiredCount = 4;
        $percentage = ($uploadedCount / $requiredCount) * 100;

        $completionStatus = [
            'uploaded' => $uploadedCount,
            'required' => $requiredCount,
            'percentage' => round($percentage),
        ];

        return $this->render('onboarding/step2.html.twig', [
            'user' => $user,
            'documents' => $documents,
            'completionStatus' => $completionStatus,
        ]);
    }

    /**
     * Finalisation de l'onboarding
     */
    #[Route('/complete', name: 'app_onboarding_complete', methods: ['POST'])]
    public function complete(#[CurrentUser] User $user): Response
    {
        try {
            $this->onboardingManager->completeOnboarding($user);

            $this->addFlash('success', 'Votre dossier a été soumis avec succès ! Il sera examiné par notre équipe RH.');

            return $this->redirectToRoute('app_onboarding_completed');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur : ' . $e->getMessage());
            return $this->redirectToRoute('app_onboarding_step2');
        }
    }

    /**
     * Page de confirmation
     */
    #[Route('/completed', name: 'app_onboarding_completed', methods: ['GET'])]
    public function completed(#[CurrentUser] User $user): Response
    {
        return $this->render('onboarding/completed.html.twig', [
            'user' => $user,
        ]);
    }
}
