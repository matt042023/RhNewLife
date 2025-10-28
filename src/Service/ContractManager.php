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
        private EventDispatcherInterface $eventDispatcher
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
        if ($parentContract && in_array($parentContract->getStatus(), [Contract::STATUS_ACTIVE, Contract::STATUS_SIGNED])) {
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
        $contract->setStatus(Contract::STATUS_SIGNED);
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
        if (!in_array($parentContract->getStatus(), [Contract::STATUS_ACTIVE, Contract::STATUS_SIGNED])) {
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
                && in_array($otherContract->getStatus(), [Contract::STATUS_ACTIVE, Contract::STATUS_SIGNED])
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
}

