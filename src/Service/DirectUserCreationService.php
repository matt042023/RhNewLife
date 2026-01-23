<?php

namespace App\Service;

use App\DTO\DirectUserCreationResult;
use App\Entity\Contract;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Service de création directe d'utilisateurs complets par l'administrateur
 * Permet de créer un utilisateur avec tous ses documents et son contrat
 * sans passer par le workflow d'onboarding
 */
class DirectUserCreationService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MatriculeGenerator $matriculeGenerator,
        private readonly DocumentManager $documentManager,
        private readonly ContractManager $contractManager,
        private readonly InvitationManager $invitationManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly LoggerInterface $logger
    ) {}

    /**
     * Crée un utilisateur complet avec documents et contrat (optionnel)
     *
     * @param array $personalData Données personnelles de l'utilisateur
     * @param array<string, UploadedFile|null> $documentFiles Fichiers de documents par type
     * @param array|null $contractData Données du contrat (null si pas de contrat)
     * @param string $activationMode Mode d'activation: 'email', 'password', ou 'none'
     * @param string|null $temporaryPassword Mot de passe temporaire (si activationMode = 'password')
     * @param User $createdBy L'administrateur qui crée l'utilisateur
     *
     * @return DirectUserCreationResult Le résultat de la création
     *
     * @throws \RuntimeException Si la création échoue de manière critique
     */
    public function createCompleteUser(
        array $personalData,
        array $documentFiles,
        ?array $contractData,
        string $activationMode,
        ?string $temporaryPassword,
        User $createdBy
    ): DirectUserCreationResult {
        return $this->entityManager->wrapInTransaction(function () use (
            $personalData,
            $documentFiles,
            $contractData,
            $activationMode,
            $temporaryPassword,
            $createdBy
        ) {
            $warnings = [];

            // 1. Créer l'utilisateur
            $user = $this->createUserEntity($personalData, $activationMode, $temporaryPassword);

            // 2. Uploader les documents
            $documents = [];
            foreach ($documentFiles as $type => $file) {
                if ($file instanceof UploadedFile && $file->isValid()) {
                    try {
                        $document = $this->documentManager->uploadDocument(
                            $file,
                            $user,
                            $type,
                            'Upload lors de la création directe par l\'administrateur',
                            $createdBy
                        );
                        $documents[] = $document;
                    } catch (\Exception $e) {
                        $warnings[] = sprintf(
                            'Document %s non uploadé: %s',
                            $this->getDocumentTypeLabel($type),
                            $e->getMessage()
                        );
                        $this->logger->warning('Document upload failed during direct creation', [
                            'type' => $type,
                            'user_id' => $user->getId(),
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }

            // 3. Créer le contrat si demandé
            $contract = null;
            if ($contractData !== null && !empty($contractData['type'])) {
                try {
                    $contract = $this->createContract($user, $contractData, $createdBy, $documentFiles);
                } catch (\Exception $e) {
                    $warnings[] = 'Contrat non créé: ' . $e->getMessage();
                    $this->logger->warning('Contract creation failed during direct creation', [
                        'user_id' => $user->getId(),
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // 4. Gérer l'activation
            $activationEmailSent = false;
            if ($activationMode === 'email') {
                try {
                    $this->invitationManager->createSimpleActivation(
                        $user->getEmail(),
                        $user->getFirstName(),
                        $user->getLastName(),
                        $user->getPosition(),
                        $user->getVilla()
                    );
                    $activationEmailSent = true;
                    // Le status reste INVITED, l'utilisateur activera son compte via le lien
                    $user->setStatus(User::STATUS_INVITED);
                } catch (\Exception $e) {
                    $warnings[] = 'Email d\'activation non envoyé: ' . $e->getMessage();
                    $this->logger->warning('Activation email failed during direct creation', [
                        'user_id' => $user->getId(),
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $this->entityManager->flush();

            $this->logger->info('Direct user creation completed', [
                'user_id' => $user->getId(),
                'matricule' => $user->getMatricule(),
                'email' => $user->getEmail(),
                'documents_count' => count($documents),
                'has_contract' => $contract !== null,
                'activation_mode' => $activationMode,
                'activation_sent' => $activationEmailSent,
                'warnings_count' => count($warnings),
                'created_by' => $createdBy->getId(),
            ]);

            return new DirectUserCreationResult(
                $user,
                $documents,
                $contract,
                $activationEmailSent,
                $warnings
            );
        });
    }

    /**
     * Crée l'entité User avec les données fournies
     */
    private function createUserEntity(
        array $data,
        string $activationMode,
        ?string $temporaryPassword
    ): User {
        $user = new User();
        $user
            ->setEmail($data['email'])
            ->setFirstName($data['firstName'])
            ->setLastName($data['lastName'])
            ->setRoles(['ROLE_USER']);

        // Définir le statut et le mot de passe selon le mode d'activation
        if ($activationMode === 'password' && $temporaryPassword) {
            // Mot de passe temporaire: compte actif mais forcer le changement
            $user->setStatus(User::STATUS_ACTIVE);
            $hashedPassword = $this->passwordHasher->hashPassword($user, $temporaryPassword);
            $user->setPassword($hashedPassword);
            $user->setForcePasswordChange(true);
        } elseif ($activationMode === 'email') {
            // Email d'activation: status INVITED, mot de passe vide
            $user->setStatus(User::STATUS_INVITED);
            $user->setPassword('');
        } else {
            // Pas d'activation: status INVITED, mot de passe vide
            $user->setStatus(User::STATUS_INVITED);
            $user->setPassword('');
        }

        // Champs optionnels
        if (!empty($data['position'])) {
            $user->setPosition($data['position']);
        }
        if (!empty($data['phone'])) {
            $user->setPhone($data['phone']);
        }
        if (!empty($data['address'])) {
            $user->setAddress($data['address']);
        }
        if (!empty($data['familyStatus'])) {
            $user->setFamilyStatus($data['familyStatus']);
        }
        if (isset($data['children'])) {
            $user->setChildren((int) $data['children']);
        }
        if (!empty($data['iban'])) {
            $user->setIban($data['iban']);
        }
        if (!empty($data['bic'])) {
            $user->setBic($data['bic']);
        }
        if (!empty($data['hiringDate'])) {
            $hiringDate = $data['hiringDate'] instanceof \DateTimeInterface
                ? $data['hiringDate']
                : new \DateTime($data['hiringDate']);
            $user->setHiringDate($hiringDate);
        }
        if (isset($data['villa'])) {
            $user->setVilla($data['villa']);
        }
        if (!empty($data['color'])) {
            $user->setColor($data['color']);
        }

        // Informations de santé
        $health = $user->getHealth();
        if (isset($data['mutuelleEnabled'])) {
            $health->setMutuelleEnabled((bool) $data['mutuelleEnabled']);
        }
        if (!empty($data['mutuelleNom'])) {
            $health->setMutuelleNom($data['mutuelleNom']);
        }
        if (!empty($data['mutuelleFormule'])) {
            $health->setMutuelleFormule($data['mutuelleFormule']);
        }
        if (!empty($data['mutuelleDateFin'])) {
            $dateFin = $data['mutuelleDateFin'] instanceof \DateTimeInterface
                ? $data['mutuelleDateFin']
                : new \DateTime($data['mutuelleDateFin']);
            $health->setMutuelleDateFin($dateFin);
        }
        if (isset($data['prevoyanceEnabled'])) {
            $health->setPrevoyanceEnabled((bool) $data['prevoyanceEnabled']);
        }
        if (!empty($data['prevoyanceNom'])) {
            $health->setPrevoyanceNom($data['prevoyanceNom']);
        }

        // Générer le matricule
        $this->matriculeGenerator->assignToUser($user);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    /**
     * Crée un contrat pour l'utilisateur
     */
    private function createContract(
        User $user,
        array $contractData,
        User $createdBy,
        array $documentFiles
    ): Contract {
        // Préparer les données du contrat
        $startDate = $contractData['startDate'] instanceof \DateTimeInterface
            ? $contractData['startDate']
            : new \DateTime($contractData['startDate']);

        $data = [
            'type' => $contractData['type'],
            'startDate' => $startDate,
            'createdBy' => $createdBy,
        ];

        if (!empty($contractData['endDate'])) {
            $data['endDate'] = $contractData['endDate'] instanceof \DateTimeInterface
                ? $contractData['endDate']
                : new \DateTime($contractData['endDate']);
        }

        if (!empty($contractData['essaiEndDate'])) {
            $data['essaiEndDate'] = $contractData['essaiEndDate'] instanceof \DateTimeInterface
                ? $contractData['essaiEndDate']
                : new \DateTime($contractData['essaiEndDate']);
        }

        if (!empty($contractData['baseSalary'])) {
            $data['baseSalary'] = $contractData['baseSalary'];
        }

        if (isset($contractData['useAnnualDaySystem'])) {
            $data['useAnnualDaySystem'] = (bool) $contractData['useAnnualDaySystem'];
        }

        if (!empty($contractData['annualDaysRequired'])) {
            $data['annualDaysRequired'] = $contractData['annualDaysRequired'];
        }

        if (isset($contractData['villa'])) {
            $data['villa'] = $contractData['villa'];
        }

        // Créer le contrat (en DRAFT)
        $contract = $this->contractManager->createContract($user, $data);

        // Si un contrat signé est fourni, le traiter
        if (isset($documentFiles['contract_signed']) && $documentFiles['contract_signed'] instanceof UploadedFile) {
            $signedFile = $documentFiles['contract_signed'];

            // Uploader le fichier signé
            $storedFileName = $this->storeContractFile($signedFile, $user);

            // Mettre à jour le contrat avec le fichier signé
            $this->contractManager->uploadSignedContract(
                $contract,
                $storedFileName,
                $signedFile->getClientOriginalName(),
                $signedFile->getMimeType(),
                $signedFile->getSize()
            );

            // Valider immédiatement le contrat
            $this->contractManager->validateSignedContract($contract, $createdBy);

            // Mettre à jour la date d'embauche si non définie
            if ($user->getHiringDate() === null) {
                $user->setHiringDate($startDate);
            }

            // Mettre le user en ACTIVE si c'était le cas
            if ($user->getStatus() === User::STATUS_INVITED || $user->getStatus() === User::STATUS_ONBOARDING) {
                $user->setStatus(User::STATUS_ACTIVE);
            }
        }

        return $contract;
    }

    /**
     * Stocke le fichier de contrat signé
     */
    private function storeContractFile(UploadedFile $file, User $user): string
    {
        $extension = $file->guessExtension() ?? 'pdf';
        $fileName = sprintf(
            'contrat_signe_%s_%s.%s',
            $user->getMatricule() ?? $user->getId(),
            date('Ymd_His'),
            $extension
        );

        $targetDirectory = sprintf('contracts/%d', $user->getId());
        $fullPath = $this->getUploadsDirectory() . '/' . $targetDirectory;

        if (!is_dir($fullPath)) {
            mkdir($fullPath, 0755, true);
        }

        $file->move($fullPath, $fileName);

        return $targetDirectory . '/' . $fileName;
    }

    /**
     * Retourne le répertoire des uploads
     */
    private function getUploadsDirectory(): string
    {
        return dirname(__DIR__, 2) . '/public/uploads';
    }

    /**
     * Retourne le label d'un type de document
     */
    private function getDocumentTypeLabel(string $type): string
    {
        $labels = [
            'cni' => 'Carte d\'identité',
            'rib' => 'RIB',
            'domicile' => 'Justificatif de domicile',
            'honorabilite' => 'Attestation d\'honorabilité',
            'diplome' => 'Diplôme',
            'contrat' => 'Contrat',
            'contract_signed' => 'Contrat signé',
        ];

        return $labels[$type] ?? $type;
    }
}
