<?php

namespace App\Service;

use App\Entity\User;
use App\Entity\Document;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Service de gestion des utilisateurs
 * Responsabilités:
 * - Création manuelle d'utilisateurs
 * - Mise à jour des profils
 * - Calcul du statut de complétion
 * - Archivage
 */
class UserManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MatriculeGenerator $matriculeGenerator,
        private InvitationManager $invitationManager,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Crée un utilisateur manuellement (par l'admin)
     * Génère automatiquement le matricule et envoie une invitation
     *
     * @param array $data Données de l'utilisateur (email, firstName, lastName, position, structure, etc.)
     * @param bool $sendInvitation Si true, envoie une invitation d'activation
     * @return User L'utilisateur créé
     */
    public function createManualUser(array $data, bool $sendInvitation = true): User
    {
        // Vérifier que l'email n'existe pas déjà
        $existingUser = $this->entityManager->getRepository(User::class)
            ->findOneBy(['email' => $data['email']]);

        if ($existingUser) {
            throw new \RuntimeException(sprintf('Un utilisateur avec l\'email "%s" existe déjà.', $data['email']));
        }

        // Créer l'utilisateur
        $user = new User();
        $user
            ->setEmail($data['email'])
            ->setFirstName($data['firstName'])
            ->setLastName($data['lastName'])
            ->setStatus(User::STATUS_INVITED)
            ->setRoles(['ROLE_USER']);

        // Champs optionnels
        if (isset($data['position'])) {
            $user->setPosition($data['position']);
        }
        if (isset($data['structure'])) {
            $user->setStructure($data['structure']);
        }
        if (isset($data['phone'])) {
            $user->setPhone($data['phone']);
        }
        if (isset($data['address'])) {
            $user->setAddress($data['address']);
        }
        if (isset($data['familyStatus'])) {
            $user->setFamilyStatus($data['familyStatus']);
        }
        if (isset($data['children'])) {
            $user->setChildren((int) $data['children']);
        }
        if (isset($data['iban'])) {
            $user->setIban($data['iban']);
        }
        if (isset($data['bic'])) {
            $user->setBic($data['bic']);
        }
        if (isset($data['hiringDate'])) {
            $user->setHiringDate($data['hiringDate']);
        }

        // Générer le matricule
        $this->matriculeGenerator->assignToUser($user);

        // Générer un mot de passe temporaire vide (sera défini lors de l'activation)
        $user->setPassword(''); // Sera mis à jour lors de l'activation

        // Sauvegarder
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        // Envoyer l'invitation si demandé
        if ($sendInvitation) {
            $this->invitationManager->createSimpleActivation(
                $user->getEmail(),
                $user->getFirstName(),
                $user->getLastName(),
                $user->getPosition(),
                $user->getStructure()
            );
        }

        $this->logger->info('Manual user created', [
            'user_id' => $user->getId(),
            'email' => $user->getEmail(),
            'matricule' => $user->getMatricule(),
            'invitation_sent' => $sendInvitation,
        ]);

        return $user;
    }

    /**
     * Met à jour le profil d'un utilisateur
     * Seuls les champs autorisés sont mis à jour
     */
    public function updateUserProfile(User $user, array $data): User
    {
        if (isset($data['phone'])) {
            $user->setPhone($data['phone']);
        }
        if (isset($data['address'])) {
            $user->setAddress($data['address']);
        }
        if (isset($data['familyStatus'])) {
            $user->setFamilyStatus($data['familyStatus']);
        }
        if (isset($data['children'])) {
            $user->setChildren((int) $data['children']);
        }
        if (isset($data['iban'])) {
            $user->setIban($data['iban']);
        }
        if (isset($data['bic'])) {
            $user->setBic($data['bic']);
        }

        // Champs admin uniquement
        if (isset($data['position'])) {
            $user->setPosition($data['position']);
        }
        if (isset($data['structure'])) {
            $user->setStructure($data['structure']);
        }
        if (isset($data['hiringDate'])) {
            $user->setHiringDate($data['hiringDate']);
        }

        $this->entityManager->flush();

        $this->logger->info('User profile updated', [
            'user_id' => $user->getId(),
        ]);

        return $user;
    }

    /**
     * Retourne le statut de complétion du profil utilisateur
     * Calcule le pourcentage de complétion et la liste des éléments manquants
     */
    public function getUserCompletionStatus(User $user): array
    {
        $requiredFields = [
            'email' => 'Adresse email',
            'firstName' => 'Prénom',
            'lastName' => 'Nom',
            'phone' => 'Téléphone',
            'address' => 'Adresse',
            'iban' => 'IBAN',
            'bic' => 'BIC',
            'position' => 'Poste',
            'structure' => 'Structure',
        ];

        $totalFields = count($requiredFields);
        $completedFields = 0;
        $missingFields = [];

        foreach ($requiredFields as $field => $label) {
            $getter = 'get' . ucfirst($field);
            $value = $user->$getter();

            if ($value !== null && $value !== '') {
                $completedFields++;
            } else {
                $missingFields[] = $label;
            }
        }

        // Vérifier les documents
        $missingDocuments = $user->getMissingDocuments();
        $hasAllDocuments = empty($missingDocuments);

        // Calculer le pourcentage global
        // 70% pour les champs, 30% pour les documents
        $fieldsPercentage = ($completedFields / $totalFields) * 70;
        $documentsPercentage = $hasAllDocuments ? 30 : 0;
        $totalPercentage = (int) ($fieldsPercentage + $documentsPercentage);

        return [
            'percentage' => $totalPercentage,
            'isComplete' => $totalPercentage === 100,
            'missingFields' => $missingFields,
            'missingDocuments' => $missingDocuments,
            'hasAllRequiredFields' => empty($missingFields),
            'hasAllDocuments' => $hasAllDocuments,
        ];
    }

    /**
     * Archive un utilisateur
     * Change le statut à ARCHIVED
     * Peut inclure une raison (départ, licenciement, etc.)
     */
    public function archiveUser(User $user, ?string $reason = null): void
    {
        if ($user->getStatus() === User::STATUS_ARCHIVED) {
            throw new \LogicException('Cet utilisateur est déjà archivé.');
        }

        $user->setStatus(User::STATUS_ARCHIVED);

        // On pourrait ajouter un champ 'archivedAt' et 'archiveReason' si besoin
        // Pour l'instant, on se contente de changer le statut

        $this->entityManager->flush();

        $this->logger->info('User archived', [
            'user_id' => $user->getId(),
            'reason' => $reason,
        ]);

        // TODO: Déclencher un event UserArchived pour notifier les autres services
    }

    /**
     * Réactive un utilisateur archivé
     */
    public function reactivateUser(User $user): void
    {
        if ($user->getStatus() !== User::STATUS_ARCHIVED) {
            throw new \LogicException('Seuls les utilisateurs archivés peuvent être réactivés.');
        }

        // Déterminer le statut approprié
        // Si l'utilisateur a des documents complets et a déjà été soumis, le remettre en actif
        // Sinon, en onboarding
        if ($user->isSubmitted() && $user->hasCompleteDocuments()) {
            $user->setStatus(User::STATUS_ACTIVE);
        } else {
            $user->setStatus(User::STATUS_ONBOARDING);
        }

        $this->entityManager->flush();

        $this->logger->info('User reactivated', [
            'user_id' => $user->getId(),
            'new_status' => $user->getStatus(),
        ]);
    }
}
