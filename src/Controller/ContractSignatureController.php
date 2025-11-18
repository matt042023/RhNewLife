<?php

namespace App\Controller;

use App\Service\ContractSignatureService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/signature-contrat')]
class ContractSignatureController extends AbstractController
{
    public function __construct(
        private ContractSignatureService $signatureService
    ) {}

    /**
     * Page de signature employé (accessible sans authentification via token)
     */
    #[Route('/{token}', name: 'app_contract_signature_sign', methods: ['GET'])]
    public function sign(string $token): Response
    {
        $contract = $this->signatureService->validateToken($token);

        if (!$contract) {
            return $this->render('contract_signature/token_expired.html.twig', [
                'token' => $token,
            ]);
        }

        return $this->render('contract_signature/sign.html.twig', [
            'contract' => $contract,
            'token' => $token,
            'employee' => $contract->getUser(),
        ]);
    }

    /**
     * Traiter la signature
     */
    #[Route('/{token}/submit', name: 'app_contract_signature_submit', methods: ['POST'])]
    public function submit(string $token, Request $request): Response
    {
        $contract = $this->signatureService->validateToken($token);

        if (!$contract) {
            return $this->render('contract_signature/token_expired.html.twig', [
                'token' => $token,
            ]);
        }

        // Vérifier la case à cocher "J'ai lu et accepte"
        if (!$request->request->get('accept_terms')) {
            $this->addFlash('error', 'Vous devez accepter les termes du contrat pour le signer.');
            return $this->redirectToRoute('app_contract_signature_sign', ['token' => $token]);
        }

        try {
            $this->signatureService->signContract($contract, $request);

            // Rediriger vers la page de confirmation (compatible Turbo)
            return $this->redirectToRoute('app_contract_signature_confirmation', [
                'token' => $token,
            ]);
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de la signature : ' . $e->getMessage());
            return $this->redirectToRoute('app_contract_signature_sign', ['token' => $token]);
        }
    }

    /**
     * Page de confirmation après signature
     */
    #[Route('/{token}/confirmation', name: 'app_contract_signature_confirmation', methods: ['GET'])]
    public function confirmation(string $token): Response
    {
        // Récupérer le contrat (même avec token expiré pour afficher la confirmation)
        $contract = $this->signatureService->getContractByToken($token);

        if (!$contract) {
            throw $this->createNotFoundException('Contrat non trouvé');
        }

        return $this->render('contract_signature/signed_confirmation.html.twig', [
            'contract' => $contract,
            'employee' => $contract->getUser(),
        ]);
    }
}
