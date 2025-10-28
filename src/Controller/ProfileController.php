<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\UserManager;
use App\Service\ProfileUpdateRequestManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/profile')]
#[IsGranted('ROLE_USER')]
class ProfileController extends AbstractController
{
    public function __construct(
        private UserManager $userManager,
        private ProfileUpdateRequestManager $profileUpdateRequestManager
    ) {
    }

    /**
     * Voir son propre profil
     */
    #[Route('', name: 'app_profile_view', methods: ['GET'])]
    public function view(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        // Récupérer le statut de complétion
        $completionStatus = $this->userManager->getUserCompletionStatus($user);

        // Récupérer le contrat actif
        $activeContract = $user->getActiveContract();

        return $this->render('profile/view.html.twig', [
            'user' => $user,
            'completionStatus' => $completionStatus,
            'activeContract' => $activeContract,
        ]);
    }

    /**
     * Formulaire de demande de modification de profil
     */
    #[Route('/edit-request', name: 'app_profile_edit_request', methods: ['GET', 'POST'])]
    public function editRequest(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if ($request->isMethod('POST')) {
            $errors = [];

            try {
                $requestedData = [];

                // Collecter les champs modifiables par l'utilisateur
                $phone = $request->request->get('phone');
                if ($phone !== null && $phone !== $user->getPhone()) {
                    $requestedData['phone'] = $phone;
                }

                $address = $request->request->get('address');
                if ($address !== null && $address !== $user->getAddress()) {
                    $requestedData['address'] = $address;
                }

                $familyStatus = $request->request->get('family_status');
                if ($familyStatus !== null && $familyStatus !== $user->getFamilyStatus()) {
                    $requestedData['familyStatus'] = $familyStatus;
                }

                $children = $request->request->get('children');
                if ($children !== null && (int)$children !== $user->getChildren()) {
                    $requestedData['children'] = (int)$children;
                }

                $iban = $request->request->get('iban');
                if ($iban !== null && $iban !== $user->getIban()) {
                    $requestedData['iban'] = $iban;
                }

                $bic = $request->request->get('bic');
                if ($bic !== null && $bic !== $user->getBic()) {
                    $requestedData['bic'] = $bic;
                }

                $reason = $request->request->get('reason');

                // Vérifier qu'il y a au moins un changement
                if (empty($requestedData)) {
                    $errors[] = 'Aucune modification détectée.';
                } else {
                    $profileUpdateRequest = $this->profileUpdateRequestManager->createRequest(
                        $user,
                        $requestedData,
                        $reason
                    );

                    $this->addFlash('success', 'Votre demande de modification a été envoyée à l\'administrateur.');
                    return $this->redirectToRoute('app_profile_view');
                }
            } catch (\Exception $e) {
                $errors[] = 'Erreur : ' . $e->getMessage();
            }

            return $this->render('profile/edit_request.html.twig', [
                'user' => $user,
                'errors' => $errors,
            ]);
        }

        return $this->render('profile/edit_request.html.twig', [
            'user' => $user,
            'errors' => [],
        ]);
    }

    /**
     * Voir ses documents
     */
    #[Route('/documents', name: 'app_profile_documents', methods: ['GET'])]
    public function documents(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $documents = $user->getDocuments();
        $missingDocuments = $user->getMissingDocuments();

        // Filtrer les documents par catégorie
        $personalDocuments = [];
        $contractDocuments = [];
        $administrativeDocuments = [];

        foreach ($documents as $document) {
            $type = $document->getType();

            // Documents personnels : CNI, RIB, Domicile, Honorabilité, Diplôme
            if (in_array($type, ['cni', 'rib', 'domicile', 'honorabilite', 'diplome'])) {
                $personalDocuments[] = $document;
            }
            // Documents contractuels : Contrat, Contrat signé, Avenant
            elseif (in_array($type, ['contrat', 'contract_signed', 'contract_amendment'])) {
                $contractDocuments[] = $document;
            }
            // Documents administratifs : Bulletin de paie, Autres
            elseif (in_array($type, ['payslip', 'other'])) {
                $administrativeDocuments[] = $document;
            }
        }

        return $this->render('profile/documents.html.twig', [
            'user' => $user,
            'documents' => $documents,
            'personalDocuments' => $personalDocuments,
            'contractDocuments' => $contractDocuments,
            'administrativeDocuments' => $administrativeDocuments,
            'missingDocuments' => $missingDocuments,
        ]);
    }

    /**
     * Voir son contrat actif
     */
    #[Route('/contract', name: 'app_profile_contract', methods: ['GET'])]
    public function contract(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $activeContract = $user->getActiveContract();
        $allContracts = $user->getContracts();

        if (!$activeContract && count($allContracts) === 0) {
            $this->addFlash('info', 'Vous n\'avez pas encore de contrat enregistré.');
        }

        return $this->render('profile/contract.html.twig', [
            'user' => $user,
            'activeContract' => $activeContract,
            'allContracts' => $allContracts,
        ]);
    }
}
