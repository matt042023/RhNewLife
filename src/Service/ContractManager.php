<?php

namespace App\Service;

use App\Domain\Event\ContractCreatedEvent;
use App\Domain\Event\ContractSignedEvent;
use App\Domain\Event\ContractClosedEvent;
use App\Entity\Contract;
use App\Entity\User;
use App\Entity\Document;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

/**
 * Service de gestion des contrats
 * Responsabilités:
 * - Création et validation des contrats
 * - Gestion des avenants (versioning)
 * - Envoi au bureau comptable
 * - Upload de contrats signés
 * - Clôture de contrats
 * - Historique et comparaison
 */
class ContractManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MailerInterface $mailer,
        private ParameterBagInterface $params,
        private EventDispatcherInterface $eventDispatcher,
        private UrlGeneratorInterface $urlGenerator,
        private Environment $twig
    ) {}

    /**
     * Crée un nouveau contrat pour un utilisateur
     * Vérifie qu'il n'y a pas déjà un contrat actif
     *
     * @throws \RuntimeException si l'utilisateur a déjà un contrat actif
     */
    public function createContract(User $user, array $data): Contract
    {
        // Vérifier qu'il n'y a pas déjà un contrat actif
        if ($this->hasActiveContract($user)) {
            throw new \RuntimeException('Cet utilisateur a déjà un contrat actif. Créez un avenant ou clôturez le contrat actuel.');
        }

        $contract = new Contract();
        $contract->setUser($user);
        $this->populateContract($contract, $data);
        $contract->setVersion(1);
        $contract->setStatus(Contract::STATUS_DRAFT);

        $this->entityManager->persist($contract);
        $this->entityManager->flush();

        // Dispatcher l'event
        $this->eventDispatcher->dispatch(
            new ContractCreatedEvent($contract),
            ContractCreatedEvent::NAME
        );

        return $contract;
    }

    /**
     * Valide un contrat brouillon
     * Passe le statut de draft à active
     * Définit la date d'embauche de l'utilisateur si elle n'existe pas
     * Si c'est un avenant, clôture automatiquement le contrat parent
     * Crée automatiquement les Documents entities pour le système de documents
     */
    public function validateContract(Contract $contract, ?User $validatedBy = null): void
    {
        if ($contract->getStatus() !== Contract::STATUS_DRAFT) {
            throw new \RuntimeException('Seuls les contrats en brouillon peuvent être validés.');
        }

        $contract->setStatus(Contract::STATUS_ACTIVE);
        $contract->setValidatedAt(new \DateTime());

        // Si c'est un avenant, clôturer le contrat parent automatiquement
        $parentContract = $contract->getParentContract();
        if ($parentContract && in_array($parentContract->getStatus(), [Contract::STATUS_ACTIVE, Contract::STATUS_SIGNED_PENDING_VALIDATION])) {
            $parentContract->setStatus(Contract::STATUS_TERMINATED);
            $parentContract->setTerminationReason('Remplacé par avenant version ' . $contract->getVersion());
            $parentContract->setTerminatedAt(new \DateTime());
        }

        // Définir la date d'embauche si elle n'existe pas
        $user = $contract->getUser();
        if ($user && $user->getHiringDate() === null) {
            $user->setHiringDate($contract->getStartDate());
        }

        // Activer l'utilisateur si nécessaire
        if ($user && $user->getStatus() === User::STATUS_ONBOARDING) {
            $user->setStatus(User::STATUS_ACTIVE);
        }

        // Créer les Document entities pour le système de documents
        // Cela permet au contrat d'apparaître dans la section "Contrat" des documents
        if ($contract->getDraftFileUrl()) {
            $this->createContractDocument($contract, Document::TYPE_CONTRAT, $validatedBy);
        }

        if ($contract->getSignedFileUrl()) {
            $this->createContractDocument($contract, Document::TYPE_CONTRACT_SIGNED, $validatedBy);
        }

        $this->entityManager->flush();
    }

    /**
     * Envoie les données du contrat au bureau comptable par email
     * Génère un email avec les informations essentielles du contrat
     */
    public function sendContractToAccounting(Contract $contract): void
    {
        if ($contract->getStatus() === Contract::STATUS_DRAFT) {
            throw new \RuntimeException('Le contrat doit être validé avant d\'être envoyé au bureau comptable.');
        }

        $accountingEmail = $this->params->get('accounting_email');
        if (!$accountingEmail) {
            throw new \RuntimeException('L\'adresse email du bureau comptable n\'est pas configurée.');
        }

        $user = $contract->getUser();

        $email = (new Email())
            ->from($this->params->get('app.mailer.sender_email'))
            ->to($accountingEmail)
            ->subject(sprintf('Nouveau contrat - %s', $user->getFullName()))
            ->html($this->generateAccountingEmailBody($contract));

        $this->mailer->send($email);

        // Marquer comme envoyé (on pourrait ajouter un champ sentToAccountingAt)
        // Pour l'instant, on laisse tel quel
    }

    /**
     * Upload d'un contrat signe (PDF)
     * Cree un Document de type CONTRACT_SIGNED lie au contrat
     * Change le statut du contrat en SIGNED
     * Envoie une notification a l'utilisateur
     */
    public function uploadSignedContract(
        Contract $contract,
        string $storedFileName,
        string $originalName,
        ?string $mimeType = null,
        ?int $fileSize = null
    ): Document {
        if ($contract->getStatus() === Contract::STATUS_DRAFT) {
            throw new \RuntimeException('Le contrat doit etre valide avant d\'uploader la version signee.');
        }

        $document = new Document();
        $document
            ->setUser($contract->getUser())
            ->setContract($contract)
            ->setType(Document::TYPE_CONTRACT_SIGNED)
            ->setFileName($storedFileName)
            ->setOriginalName($originalName)
            ->setMimeType($mimeType)
            ->setFileSize($fileSize);

        $contract->addDocument($document);
        $contract->setStatus(Contract::STATUS_SIGNED_PENDING_VALIDATION);
        $contract->setSignedAt(new \DateTime());

        $this->entityManager->persist($document);
        $this->entityManager->flush();

        $this->eventDispatcher->dispatch(
            new ContractSignedEvent($contract),
            ContractSignedEvent::NAME
        );

        return $document;
    }

    /**
     * Crée un avenant (nouvelle version du contrat)
     * Archive l'ancien contrat et crée une nouvelle version
     */
    public function createAmendment(Contract $parentContract, array $data, ?string $reason = null): Contract
    {
        if (!in_array($parentContract->getStatus(), [Contract::STATUS_ACTIVE, Contract::STATUS_SIGNED_PENDING_VALIDATION])) {
            throw new \RuntimeException('Seuls les contrats actifs ou signés peuvent faire l\'objet d\'un avenant.');
        }

        // Créer le nouvel avenant
        $amendment = new Contract();
        $amendment->setUser($parentContract->getUser());
        $amendment->setParentContract($parentContract);
        $amendment->setVersion($parentContract->getVersion() + 1);
        $amendment->setStatus(Contract::STATUS_DRAFT);

        // Copier les données du parent puis appliquer les modifications
        $this->copyContractData($parentContract, $amendment);
        $this->populateContract($amendment, $data);

        // Optionnel: stocker la raison de l'avenant
        // On pourrait ajouter un champ 'amendmentReason' si besoin

        $this->entityManager->persist($amendment);
        $this->entityManager->flush();

        return $amendment;
    }

    /**
     * Clôture un contrat
     * Marque le contrat comme terminé avec raison et date
     * Déclenche l'offboarding de l'utilisateur
     */
    public function closeContract(Contract $contract, string $reason, \DateTimeInterface $terminationDate): void
    {
        if ($contract->getStatus() === Contract::STATUS_TERMINATED) {
            throw new \RuntimeException('Ce contrat est déjà clôturé.');
        }

        $contract->setStatus(Contract::STATUS_TERMINATED);
        $contract->setTerminationReason($reason);
        $contract->setTerminatedAt($terminationDate);

        $user = $contract->getUser();

        // Vérifier si l'utilisateur a d'autres contrats actifs
        $hasOtherActiveContracts = false;
        foreach ($user->getContracts() as $otherContract) {
            if (
                $otherContract->getId() !== $contract->getId()
                && in_array($otherContract->getStatus(), [Contract::STATUS_ACTIVE, Contract::STATUS_SIGNED_PENDING_VALIDATION])
            ) {
                $hasOtherActiveContracts = true;
                break;
            }
        }

        // Si aucun autre contrat actif, archiver l'utilisateur
        if (!$hasOtherActiveContracts) {
            $user->setStatus(User::STATUS_ARCHIVED);
        }

        $this->entityManager->flush();

        // Dispatcher l'event pour déclencher l'offboarding
        $this->eventDispatcher->dispatch(
            new ContractClosedEvent($contract, $reason),
            ContractClosedEvent::NAME
        );
    }

    /**
     * Retourne l'historique complet des contrats d'un utilisateur
     * Trié par version décroissante
     */
    public function getContractHistory(User $user): array
    {
        return $this->entityManager
            ->getRepository(Contract::class)
            ->findAllByUser($user);
    }

    /**
     * Compare deux versions de contrat
     * Retourne un tableau avec les différences
     */
    public function compareContractVersions(Contract $contract1, Contract $contract2): array
    {
        $diff = [];

        $fields = [
            'type' => 'Type de contrat',
            'startDate' => 'Date de début',
            'endDate' => 'Date de fin',
            'essaiEndDate' => 'Fin période d\'essai',
            'baseSalary' => 'Salaire de base',
            'activityRate' => 'Taux d\'activité',
            'weeklyHours' => 'Heures hebdomadaires',
            'villa' => 'Villa affectée',
            'workingDays' => 'Jours travaillés',
        ];

        foreach ($fields as $field => $label) {
            $getter = 'get' . ucfirst($field);

            $value1 = $contract1->$getter();
            $value2 = $contract2->$getter();

            // Normaliser les dates pour comparaison
            if ($value1 instanceof \DateTimeInterface) {
                $value1 = $value1->format('Y-m-d');
            }
            if ($value2 instanceof \DateTimeInterface) {
                $value2 = $value2->format('Y-m-d');
            }

            // Normaliser les tableaux JSON
            if (is_array($value1)) {
                $value1 = json_encode($value1);
            }
            if (is_array($value2)) {
                $value2 = json_encode($value2);
            }

            if ($value1 != $value2) {
                $diff[$field] = [
                    'label' => $label,
                    'old' => $value1,
                    'new' => $value2,
                ];
            }
        }

        return $diff;
    }

    /**
     * Vérifie si un utilisateur a un contrat actif
     */
    private function hasActiveContract(User $user): bool
    {
        return $this->entityManager
            ->getRepository(Contract::class)
            ->hasActiveContract($user);
    }

    /**
     * Remplit un contrat avec des données
     */
    private function populateContract(Contract $contract, array $data): void
    {
        if (isset($data['type'])) {
            $contract->setType($data['type']);
        }
        if (isset($data['startDate'])) {
            $contract->setStartDate($data['startDate']);
        }
        if (isset($data['endDate'])) {
            $contract->setEndDate($data['endDate']);
        }
        if (isset($data['essaiEndDate'])) {
            $contract->setEssaiEndDate($data['essaiEndDate']);
        }
        if (isset($data['baseSalary'])) {
            $contract->setBaseSalary($data['baseSalary']);
        }
        if (isset($data['activityRate'])) {
            $contract->setActivityRate($data['activityRate']);
        }
        if (isset($data['weeklyHours'])) {
            $contract->setWeeklyHours($data['weeklyHours']);
        }
        if (isset($data['villa'])) {
            $contract->setVilla($data['villa']);
        }
        if (isset($data['workingDays'])) {
            $contract->setWorkingDays($data['workingDays']);
        }
        if (isset($data['createdBy'])) {
            $contract->setCreatedBy($data['createdBy']);
        }
    }

    /**
     * Copie les données d'un contrat vers un autre
     */
    private function copyContractData(Contract $source, Contract $target): void
    {
        $target->setType($source->getType());
        $target->setStartDate($source->getStartDate());
        $target->setEndDate($source->getEndDate());
        $target->setEssaiEndDate($source->getEssaiEndDate());
        $target->setBaseSalary($source->getBaseSalary());
        $target->setActivityRate($source->getActivityRate());
        $target->setWeeklyHours($source->getWeeklyHours());
        $target->setVilla($source->getVilla());
        $target->setWorkingDays($source->getWorkingDays());
    }

    /**
     * Génère le corps de l'email pour le bureau comptable
     */
    private function generateAccountingEmailBody(Contract $contract): string
    {
        $user = $contract->getUser();

        return sprintf(
            '<h2>Nouveau contrat à traiter</h2>
            <p><strong>Salarié:</strong> %s</p>
            <p><strong>Matricule:</strong> %s</p>
            <p><strong>Type de contrat:</strong> %s</p>
            <p><strong>Date de début:</strong> %s</p>
            <p><strong>Date de fin:</strong> %s</p>
            <p><strong>Période d\'essai jusqu\'au:</strong> %s</p>
            <p><strong>Salaire de base:</strong> %s €</p>
            <p><strong>Taux d\'activité:</strong> %s</p>
            <p><strong>Heures hebdomadaires:</strong> %s h</p>
            <p><strong>Villa affectée:</strong> %s</p>
            <p><strong>IBAN:</strong> %s</p>
            <p><strong>BIC:</strong> %s</p>
            <p>Merci de préparer les documents nécessaires pour ce nouveau contrat.</p>',
            $user->getFullName(),
            $user->getMatricule() ?? 'Non défini',
            $contract->getType(),
            $contract->getStartDate()?->format('d/m/Y') ?? 'Non défini',
            $contract->getEndDate()?->format('d/m/Y') ?? 'Non défini',
            $contract->getEssaiEndDate()?->format('d/m/Y') ?? 'Non applicable',
            $contract->getBaseSalary() ?? '0.00',
            $contract->getActivityRate() ?? '1.00',
            $contract->getWeeklyHours() ?? 'Non défini',
            $contract->getVilla() ?? 'Non affecté',
            $user->getIban() ?? 'Non renseigné',
            $user->getBic() ?? 'Non renseigné'
        );
    }

    // ===== NOUVELLES MÉTHODES WF09 =====

    /**
     * Crée un contrat depuis un template
     * Génère automatiquement le brouillon PDF
     */
    public function createContractFromTemplate(
        User $user,
        \App\Entity\TemplateContrat $template,
        array $data,
        \App\Service\ContractGeneratorService $generator
    ): Contract {
        // Vérifier s'il y a un contrat parent (avenant)
        $isAmendment = isset($data['parentContract']);

        // Vérifier qu'il n'y a pas déjà un contrat actif (sauf si c'est un avenant)
        if (!$isAmendment && $this->hasActiveContract($user)) {
            throw new \RuntimeException('Cet utilisateur a déjà un contrat actif. Créez un avenant ou clôturez le contrat actuel.');
        }

        // Créer le contrat
        $contract = new Contract();
        $contract->setUser($user);
        $contract->setTemplate($template);

        // Si c'est un avenant, établir la relation parent-enfant
        if ($isAmendment) {
            $contract->setParentContract($data['parentContract']);
            $contract->setVersion($data['version']);
            // Retirer ces champs du tableau $data pour éviter les erreurs dans populateContract
            unset($data['parentContract'], $data['version']);
        } else {
            $contract->setVersion(1);
        }

        $this->populateContract($contract, $data);
        $contract->setStatus(Contract::STATUS_DRAFT);

        $this->entityManager->persist($contract);
        $this->entityManager->flush();

        // Générer le brouillon PDF
        try {
            $draftUrl = $generator->generateDraftContract($contract, $template);
            $contract->setDraftFileUrl($draftUrl);
            $this->entityManager->flush();
        } catch (\Exception $e) {
            // Le contrat a été créé mais la génération du PDF a échoué
            // On peut décider de supprimer le contrat ou de laisser sans PDF
            throw new \RuntimeException('Erreur lors de la génération du brouillon: ' . $e->getMessage(), 0, $e);
        }

        // Dispatcher l'event
        $this->eventDispatcher->dispatch(
            new \App\Domain\Event\ContractCreatedEvent($contract),
            \App\Domain\Event\ContractCreatedEvent::NAME
        );

        return $contract;
    }

    /**
     * Valide un contrat signé par l'employé (action admin)
     * Crée automatiquement les Documents entities pour le système de documents
     */
    public function validateSignedContract(Contract $contract, User $admin): void
    {
        if ($contract->getStatus() !== Contract::STATUS_SIGNED_PENDING_VALIDATION) {
            throw new \RuntimeException('Seuls les contrats signés en attente de validation peuvent être validés.');
        }

        // Passer le contrat à ACTIVE
        $contract->setStatus(Contract::STATUS_ACTIVE);
        $contract->setValidatedBy($admin);
        $contract->setValidatedAt(new \DateTime());

        // Si ce contrat remplace un autre (avenant), archiver l'ancien
        $parentContract = $contract->getParentContract();
        if ($parentContract && in_array($parentContract->getStatus(), [Contract::STATUS_ACTIVE, Contract::STATUS_SIGNED_PENDING_VALIDATION])) {
            $parentContract->setStatus(Contract::STATUS_REPLACED);
            $parentContract->setTerminationReason('Remplacé par avenant version ' . $contract->getVersion());
            $parentContract->setTerminatedAt(new \DateTime());
        }

        // Définir la date d'embauche si elle n'existe pas
        $user = $contract->getUser();
        if ($user && $user->getHiringDate() === null) {
            $user->setHiringDate($contract->getStartDate());
        }

        // Activer l'utilisateur si nécessaire
        if ($user && $user->getStatus() === \App\Entity\User::STATUS_ONBOARDING) {
            $user->setStatus(\App\Entity\User::STATUS_ACTIVE);
        }

        // Créer les Document entities pour le système de documents
        // Cela permet au contrat d'apparaître dans la section "Contrat" des documents
        if ($contract->getDraftFileUrl()) {
            $this->createContractDocument($contract, Document::TYPE_CONTRAT, $admin);
        }

        if ($contract->getSignedFileUrl()) {
            $this->createContractDocument($contract, Document::TYPE_CONTRACT_SIGNED, $admin);
        }

        $this->entityManager->flush();

        // TODO: Envoyer email de notification à l'employé
    }

    /**
     * Rejette un contrat signé (uploadé) et demande un nouvel upload
     *
     * @param Contract $contract Le contrat à rejeter
     * @param string $reason La raison du rejet
     * @param User $admin L'administrateur qui rejette le contrat
     *
     * @return void
     * @throws \RuntimeException Si le contrat n'est pas en statut SIGNED_PENDING_VALIDATION
     */
    public function rejectSignedContract(Contract $contract, string $reason, User $admin): void
    {
        if ($contract->getStatus() !== Contract::STATUS_SIGNED_PENDING_VALIDATION) {
            throw new \RuntimeException('Seuls les contrats signés en attente de validation peuvent être rejetés.');
        }

        // Retour au statut PENDING_SIGNATURE pour permettre un nouvel upload
        $contract->setStatus(Contract::STATUS_PENDING_SIGNATURE);

        // Générer un nouveau token de signature (7 jours de validité)
        $token = bin2hex(random_bytes(32));
        $contract->setSignatureToken($token);
        $contract->setTokenExpiresAt((new \DateTime())->modify('+7 days'));

        // Supprimer le fichier signé précédent (optionnel)
        if ($contract->getSignedFileUrl()) {
            $oldFilePath = $this->params->get('kernel.project_dir') . '/public/uploads/' . $contract->getSignedFileUrl();
            if (file_exists($oldFilePath)) {
                @unlink($oldFilePath); // Supprimer sans erreur si fichier inexistant
            }
            $contract->setSignedFileUrl(null);
        }

        // Réinitialiser les métadonnées de signature
        $contract->setSignedAt(null);
        $contract->setSignatureIp(null);
        $contract->setSignatureUserAgent(null);

        $this->entityManager->flush();

        // Envoyer l'email de rejet à l'employé
        $this->sendRejectionEmail($contract, $reason, $token);
    }

    /**
     * Envoie l'email de rejet à l'employé
     */
    private function sendRejectionEmail(Contract $contract, string $reason, string $token): void
    {
        $employee = $contract->getUser();

        // Générer l'URL d'upload
        $uploadUrl = $this->urlGenerator->generate(
            'app_contract_signature_sign',
            ['token' => $token],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        // Render email template
        $emailHtml = $this->twig->render('emails/contract/signature_rejected.html.twig', [
            'employee' => $employee,
            'contract' => $contract,
            'reason' => $reason,
            'uploadUrl' => $uploadUrl,
            'tokenExpiresAt' => $contract->getTokenExpiresAt(),
        ]);

        $email = (new Email())
            ->from($this->params->get('app.mailer.sender_email'))
            ->to($employee->getEmail())
            ->subject(sprintf('Contrat à resigner - %s', $contract->getTypeLabel()))
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
     * Annule un contrat
     */
    public function cancelContract(Contract $contract, string $reason): void
    {
        if (!$contract->canBeCancelled()) {
            throw new \RuntimeException('Ce contrat ne peut pas être annulé dans son état actuel.');
        }

        $contract->setStatus(Contract::STATUS_CANCELLED);
        $contract->setTerminationReason($reason);
        $contract->setTerminatedAt(new \DateTime());

        // Invalider le token de signature si existant
        if ($contract->getSignatureToken()) {
            $contract->setSignatureToken(null);
            $contract->setTokenExpiresAt(null);
        }

        $this->entityManager->flush();

        // TODO: Envoyer email de notification si nécessaire
    }

    /**
     * Archive un contrat
     */
    public function archiveContract(Contract $contract): void
    {
        if ($contract->getStatus() === Contract::STATUS_ARCHIVED) {
            throw new \RuntimeException('Ce contrat est déjà archivé.');
        }

        $contract->setStatus(Contract::STATUS_ARCHIVED);
        $contract->setTerminatedAt(new \DateTime());

        $this->entityManager->flush();
    }

    /**
     * Crée des Document entities pour un contrat existant
     * Utile pour la migration de données ou pour corriger des contrats sans documents
     *
     * @param Contract $contract Le contrat pour lequel créer les documents
     * @param User|null $createdBy L'utilisateur qui effectue l'opération (généralement un admin)
     * @return array Les documents créés
     */
    public function createDocumentsForContract(Contract $contract, ?User $createdBy = null): array
    {
        $documents = [];

        // Créer le document pour le brouillon si disponible
        if ($contract->getDraftFileUrl()) {
            try {
                $documents[] = $this->createContractDocument($contract, Document::TYPE_CONTRAT, $createdBy);
            } catch (\RuntimeException $e) {
                // Ignorer si le document existe déjà ou en cas d'erreur
            }
        }

        // Créer le document pour le contrat signé si disponible
        if ($contract->getSignedFileUrl()) {
            try {
                $documents[] = $this->createContractDocument($contract, Document::TYPE_CONTRACT_SIGNED, $createdBy);
            } catch (\RuntimeException $e) {
                // Ignorer si le document existe déjà ou en cas d'erreur
            }
        }

        $this->entityManager->flush();

        return $documents;
    }

    /**
     * Crée un Document entity à partir d'un contrat
     * Permet de lier le système de contrats au système de documents
     *
     * @param Contract $contract Le contrat source
     * @param string $type Le type de document (TYPE_CONTRAT ou TYPE_CONTRACT_SIGNED)
     * @param User|null $createdBy L'utilisateur qui crée le document (généralement l'admin)
     * @return Document Le document créé
     */
    private function createContractDocument(Contract $contract, string $type, ?User $createdBy = null): Document
    {
        // Vérifier si le document existe déjà pour ce contrat
        $existingDoc = $this->entityManager->getRepository(Document::class)
            ->findOneBy([
                'user' => $contract->getUser(),
                'type' => $type,
                'contract' => $contract
            ]);

        if ($existingDoc) {
            return $existingDoc;
        }

        // Déterminer le fichier à utiliser selon le type
        $fileUrl = $type === Document::TYPE_CONTRAT
            ? $contract->getDraftFileUrl()
            : $contract->getSignedFileUrl();

        if (!$fileUrl) {
            throw new \RuntimeException(
                sprintf('Impossible de créer le document %s : fichier manquant', $type)
            );
        }

        // Créer le document
        // Note: Pour les contrats, on stocke le chemin relatif complet (ex: contracts/file.pdf)
        // car les fichiers ne sont pas dans le dossier documents/users/{matricule}/
        $document = new Document();
        $document
            ->setUser($contract->getUser())
            ->setContract($contract)
            ->setType($type)
            ->setFileName($fileUrl) // Chemin relatif complet depuis uploads/
            ->setOriginalName(basename($fileUrl))
            ->setStatus(Document::STATUS_VALIDATED) // Auto-validé car créé par le système
            ->setValidatedBy($createdBy)
            ->setValidatedAt(new \DateTime())
            ->setUploadedBy($createdBy);

        // Déterminer la taille du fichier si possible
        $projectDir = $this->params->get('kernel.project_dir');
        $filePath = $projectDir . '/public/uploads/' . $fileUrl;

        if (file_exists($filePath)) {
            $document->setFileSize(filesize($filePath));
            $document->setMimeType('application/pdf');
        }

        $this->entityManager->persist($document);

        return $document;
    }
}

