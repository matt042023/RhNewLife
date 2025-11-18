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
     * Traiter l'upload du contrat signé manuellement
     */
    #[Route('/{token}/upload', name: 'app_contract_signature_upload', methods: ['POST'])]
    public function uploadSigned(string $token, Request $request): Response
    {
        $contract = $this->signatureService->validateToken($token);

        if (!$contract) {
            return $this->render('contract_signature/token_expired.html.twig', [
                'token' => $token,
            ]);
        }

        // Récupérer le fichier uploadé
        $file = $request->files->get('signed_contract');

        if (!$file) {
            $this->addFlash('error', 'Aucun fichier n\'a été uploadé.');
            return $this->redirectToRoute('app_contract_signature_sign', ['token' => $token]);
        }

        // Valider le fichier
        $maxSize = 10 * 1024 * 1024; // 10 Mo
        if ($file->getSize() > $maxSize) {
            $this->addFlash('error', 'Le fichier est trop volumineux. Taille maximum : 10 Mo.');
            return $this->redirectToRoute('app_contract_signature_sign', ['token' => $token]);
        }

        // Vérifier le type MIME
        $allowedMimeTypes = ['application/pdf'];
        if (!in_array($file->getMimeType(), $allowedMimeTypes)) {
            $this->addFlash('error', 'Le fichier doit être au format PDF.');
            return $this->redirectToRoute('app_contract_signature_sign', ['token' => $token]);
        }

        try {
            $this->signatureService->handleUploadedContract($contract, $file, $request);

            $this->addFlash('success', 'Votre contrat signé a été uploadé avec succès !');

            // Rediriger vers la page de confirmation (compatible Turbo)
            return $this->redirectToRoute('app_contract_signature_confirmation', [
                'token' => $token,
            ]);
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de l\'upload : ' . $e->getMessage());
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
