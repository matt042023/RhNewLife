<?php

namespace App\Service;

use App\Entity\Contract;
use App\Repository\ContractRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Twig\Environment;

/**
 * Service de gestion de la signature électronique des contrats
 * Responsabilités:
 * - Générer tokens de signature sécurisés
 * - Envoyer emails de demande de signature
 * - Valider tokens
 * - Traiter signatures employés
 * - Générer PDF signés
 */
class ContractSignatureService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ContractRepository $contractRepository,
        private MailerInterface $mailer,
        private ContractGeneratorService $generator,
        private UrlGeneratorInterface $urlGenerator,
        private ParameterBagInterface $params,
        private Environment $twig
    ) {}

    /**
     * Envoie un contrat pour signature
     * Génère un token unique et envoie l'email à l'employé
     */
    public function sendForSignature(Contract $contract): void
    {
        if ($contract->getStatus() !== Contract::STATUS_DRAFT) {
            throw new \RuntimeException('Seuls les contrats en brouillon peuvent être envoyés pour signature.');
        }

        if (!$contract->getDraftFileUrl()) {
            throw new \RuntimeException('Le contrat doit avoir un brouillon généré avant l\'envoi pour signature.');
        }

        // Générer un token unique
        $token = $this->generateUniqueToken();

        // Définir l'expiration (7 jours par défaut)
        $expiresAt = new \DateTime('+7 days');

        // Mettre à jour le contrat
        $contract->setSignatureToken($token);
        $contract->setTokenExpiresAt($expiresAt);
        $contract->setStatus(Contract::STATUS_PENDING_SIGNATURE);

        $this->entityManager->flush();

        // Envoyer l'email de signature
        $this->sendSignatureEmail($contract, $token);
    }

    /**
     * Génère un token unique pour la signature
     */
    private function generateUniqueToken(): string
    {
        $maxAttempts = 10;
        $attempt = 0;

        do {
            $token = bin2hex(random_bytes(32)); // 64 caractères hexadécimaux
            $existing = $this->contractRepository->findBySignatureToken($token);
            $attempt++;

            if ($attempt >= $maxAttempts) {
                throw new \RuntimeException('Impossible de générer un token unique après ' . $maxAttempts . ' tentatives');
            }
        } while ($existing !== null);

        return $token;
    }

    /**
     * Envoie l'email de demande de signature
     */
    private function sendSignatureEmail(Contract $contract, string $token): void
    {
        $employee = $contract->getUser();

        // Générer l'URL d'upload (au lieu de signature électronique)
        $uploadUrl = $this->urlGenerator->generate(
            'app_contract_signature_sign',
            ['token' => $token],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        // Render email template
        $emailHtml = $this->twig->render('emails/contract/signature_request.html.twig', [
            'employee' => $employee,
            'contract' => $contract,
            'uploadUrl' => $uploadUrl,
            'tokenExpiresAt' => $contract->getTokenExpiresAt(),
        ]);

        $email = (new Email())
            ->from($this->params->get('app.mailer.sender_email'))
            ->to($employee->getEmail())
            ->subject(sprintf('Contrat de travail à signer - %s', $contract->getTypeLabel()))
            ->html($emailHtml);

        // Attacher le PDF brouillon du contrat
        if ($contract->getDraftFileUrl()) {
            $projectDir = $this->params->get('kernel.project_dir');
            $draftFilePath = $projectDir . '/public/uploads/' . $contract->getDraftFileUrl();

            if (file_exists($draftFilePath)) {
                $email->attachFromPath(
                    $draftFilePath,
                    sprintf('Contrat_%s_%s.pdf', $contract->getTypeLabel(), $employee->getLastName())
                );
            }
        }

        $this->mailer->send($email);
    }

    /**
     * Valide un token de signature
     * Retourne le contrat associé ou null si invalide/expiré
     */
    public function validateToken(string $token): ?Contract
    {
        $contract = $this->contractRepository->findBySignatureToken($token);

        if (!$contract) {
            return null;
        }

        // Vérifier l'expiration
        if (!$contract->isSignatureTokenValid()) {
            return null;
        }

        // Vérifier le statut
        if ($contract->getStatus() !== Contract::STATUS_PENDING_SIGNATURE) {
            return null;
        }

        return $contract;
    }

    /**
     * Récupère un contrat par son token (même si expiré ou déjà signé)
     * Utilisé pour afficher la page de confirmation après signature
     */
    public function getContractByToken(string $token): ?Contract
    {
        $contract = $this->contractRepository->findBySignatureToken($token);

        if (!$contract) {
            return null;
        }

        // Accepter les contrats en attente de signature ou signés en attente de validation
        if (!in_array($contract->getStatus(), [
            Contract::STATUS_PENDING_SIGNATURE,
            Contract::STATUS_SIGNED_PENDING_VALIDATION,
        ])) {
            return null;
        }

        return $contract;
    }

    /**
     * Traite la signature d'un contrat par l'employé
     */
    public function signContract(Contract $contract, Request $request): void
    {
        if (!$contract->canBeSigned()) {
            throw new \RuntimeException('Ce contrat ne peut pas être signé dans son état actuel.');
        }

        // Capturer les métadonnées de signature
        $ip = $request->getClientIp();
        $userAgent = $request->headers->get('User-Agent');
        $signedAt = new \DateTime();

        // Sauvegarder les métadonnées
        $contract->setSignatureIp($ip);
        $contract->setSignatureUserAgent($userAgent);
        $contract->setSignedAt($signedAt);

        // Générer le PDF signé
        $signatureData = [
            'signedAt' => $signedAt,
            'employeeName' => $contract->getUser()->getFullName(),
            'ip' => $ip,
            'userAgent' => $userAgent,
            'documentHash' => $this->generator->calculateDocumentHash(
                $contract->getDraftFileUrl() ?? ''
            ),
        ];

        $signedPdfPath = $this->generator->generateSignedPdf($contract, $signatureData);
        $contract->setSignedFileUrl($signedPdfPath);

        // Changer le statut
        $contract->setStatus(Contract::STATUS_SIGNED_PENDING_VALIDATION);

        // Ne pas invalider le token tout de suite pour permettre l'accès à la page de confirmation
        // Le token sera invalidé par un cron job ou manuellement plus tard

        $this->entityManager->flush();

        // Notifier l'admin
        $this->notifyAdminContractSigned($contract);
    }

    /**
     * Traite l'upload d'un contrat signé manuellement
     *
     * @param Contract $contract Le contrat concerné
     * @param \Symfony\Component\HttpFoundation\File\UploadedFile $file Le fichier PDF uploadé
     * @param Request $request La requête HTTP pour capturer les métadonnées
     *
     * @return void
     * @throws \RuntimeException Si le contrat n'est pas en statut PENDING_SIGNATURE
     */
    public function handleUploadedContract(Contract $contract, $file, Request $request): void
    {
        // Vérifier que le contrat est en attente de signature
        if ($contract->getStatus() !== Contract::STATUS_PENDING_SIGNATURE) {
            throw new \RuntimeException('Ce contrat n\'est pas en attente de signature.');
        }

        // Générer un nom de fichier unique et sécurisé
        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = transliterator_transliterate(
            'Any-Latin; Latin-ASCII; [^A-Za-z0-9_] remove; Lower()',
            $originalFilename
        );
        $newFilename = sprintf(
            'contract_signed_%d_%s_%s.pdf',
            $contract->getId() ?? uniqid(),
            $safeFilename,
            uniqid()
        );

        // Déplacer le fichier dans le répertoire de stockage
        $projectDir = $this->params->get('kernel.project_dir');
        $uploadDir = $projectDir . '/public/uploads/contracts/signed';

        // Créer le répertoire si nécessaire
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $file->move($uploadDir, $newFilename);

        // Enregistrer le chemin relatif
        $relativePath = 'contracts/signed/' . $newFilename;
        $contract->setSignedFileUrl($relativePath);

        // Capturer les métadonnées d'upload
        $contract->setSignedAt(new \DateTime());
        $contract->setSignatureIp($request->getClientIp());
        $contract->setSignatureUserAgent($request->headers->get('User-Agent'));

        // Changer le statut
        $contract->setStatus(Contract::STATUS_SIGNED_PENDING_VALIDATION);

        // Ne pas invalider le token tout de suite pour permettre l'accès à la page de confirmation
        // Le token sera invalidé par un cron job ou manuellement plus tard

        $this->entityManager->flush();

        // Notifier l'admin
        $this->notifyAdminContractSigned($contract);
    }

    /**
     * Invalide le token de signature
     */
    public function invalidateToken(Contract $contract): void
    {
        $contract->setSignatureToken(null);
        $contract->setTokenExpiresAt(null);
    }

    /**
     * Notifie l'admin qu'un contrat a été signé
     */
    private function notifyAdminContractSigned(Contract $contract): void
    {
        $adminEmail = $this->params->get('app.mailer.admin_email');

        if (!$adminEmail) {
            return; // Pas d'email admin configuré
        }

        $emailHtml = $this->twig->render('emails/contract/signed_notification.html.twig', [
            'contract' => $contract,
            'employee' => $contract->getUser(),
            'contractUrl' => $this->urlGenerator->generate(
                'app_admin_contracts_view',
                ['id' => $contract->getId()],
                UrlGeneratorInterface::ABSOLUTE_URL
            ),
        ]);

        $email = (new Email())
            ->from($this->params->get('app.mailer.sender_email'))
            ->to($adminEmail)
            ->subject(sprintf('Contrat signé - %s', $contract->getUser()->getFullName()))
            ->html($emailHtml);

        $this->mailer->send($email);
    }

    /**
     * Renvoie un email de signature (token expiré ou perdu)
     */
    public function resendSignatureEmail(Contract $contract): void
    {
        if ($contract->getStatus() !== Contract::STATUS_PENDING_SIGNATURE) {
            throw new \RuntimeException('Ce contrat n\'est pas en attente de signature.');
        }

        // Générer un nouveau token
        $token = $this->generateUniqueToken();
        $expiresAt = new \DateTime('+7 days');

        $contract->setSignatureToken($token);
        $contract->setTokenExpiresAt($expiresAt);

        $this->entityManager->flush();

        // Renvoyer l'email
        $this->sendSignatureEmail($contract, $token);
    }

    /**
     * Nettoie les tokens expirés (tâche cron)
     */
    public function cleanExpiredTokens(): int
    {
        $expiredContracts = $this->contractRepository->findExpiredTokens();
        $count = 0;

        foreach ($expiredContracts as $contract) {
            $this->invalidateToken($contract);
            $count++;
        }

        if ($count > 0) {
            $this->entityManager->flush();
        }

        return $count;
    }

    /**
     * Annule une demande de signature
     */
    public function cancelSignatureRequest(Contract $contract): void
    {
        if ($contract->getStatus() !== Contract::STATUS_PENDING_SIGNATURE) {
            throw new \RuntimeException('Seules les demandes de signature en attente peuvent être annulées.');
        }

        $this->invalidateToken($contract);
        $contract->setStatus(Contract::STATUS_DRAFT);

        $this->entityManager->flush();
    }

    /**
     * Obtient des statistiques sur les signatures
     */
    public function getSignatureStats(): array
    {
        $pendingSignature = $this->contractRepository->findPendingSignature();
        $pendingValidation = $this->contractRepository->findPendingValidation();
        $expiredTokens = $this->contractRepository->findExpiredTokens();

        return [
            'pendingSignature' => count($pendingSignature),
            'pendingValidation' => count($pendingValidation),
            'expiredTokens' => count($expiredTokens),
        ];
    }
}
