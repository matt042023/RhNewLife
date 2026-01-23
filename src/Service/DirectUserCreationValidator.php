<?php

namespace App\Service;

use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class DirectUserCreationValidator
{
    private const ALLOWED_MIME_TYPES = [
        'application/pdf',
        'image/jpeg',
        'image/png',
    ];

    private const MAX_FILE_SIZE = 10 * 1024 * 1024; // 10 MB

    public function __construct(
        private readonly UserRepository $userRepository
    ) {}

    /**
     * Valide les données personnelles de l'utilisateur
     * @return array<string, string> Les erreurs par champ
     */
    public function validatePersonalData(array $data): array
    {
        $errors = [];

        // Champs obligatoires
        if (empty($data['email'])) {
            $errors['email'] = 'L\'email est obligatoire.';
        } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'L\'email n\'est pas valide.';
        } elseif ($this->userRepository->findOneBy(['email' => $data['email']])) {
            $errors['email'] = 'Cet email est déjà utilisé par un autre utilisateur.';
        }

        if (empty($data['firstName'])) {
            $errors['firstName'] = 'Le prénom est obligatoire.';
        } elseif (strlen($data['firstName']) > 100) {
            $errors['firstName'] = 'Le prénom ne doit pas dépasser 100 caractères.';
        }

        if (empty($data['lastName'])) {
            $errors['lastName'] = 'Le nom est obligatoire.';
        } elseif (strlen($data['lastName']) > 100) {
            $errors['lastName'] = 'Le nom ne doit pas dépasser 100 caractères.';
        }

        if (empty($data['position'])) {
            $errors['position'] = 'Le poste est obligatoire.';
        } elseif (strlen($data['position']) > 100) {
            $errors['position'] = 'Le poste ne doit pas dépasser 100 caractères.';
        }

        // Champs optionnels avec validation
        if (!empty($data['phone']) && strlen($data['phone']) > 20) {
            $errors['phone'] = 'Le numéro de téléphone ne doit pas dépasser 20 caractères.';
        }

        if (!empty($data['iban']) && !$this->isValidIban($data['iban'])) {
            $errors['iban'] = 'L\'IBAN n\'est pas valide.';
        }

        if (!empty($data['bic']) && !$this->isValidBic($data['bic'])) {
            $errors['bic'] = 'Le BIC n\'est pas valide.';
        }

        if (!empty($data['hiringDate'])) {
            if ($data['hiringDate'] instanceof \DateTimeInterface) {
                // OK
            } elseif (is_string($data['hiringDate'])) {
                try {
                    new \DateTime($data['hiringDate']);
                } catch (\Exception $e) {
                    $errors['hiringDate'] = 'La date d\'embauche n\'est pas valide.';
                }
            }
        }

        return $errors;
    }

    /**
     * Valide les données du contrat
     * @return array<string, string> Les erreurs par champ
     */
    public function validateContractData(array $data): array
    {
        $errors = [];

        // Si pas de type, pas de contrat à créer
        if (empty($data['type'])) {
            return $errors;
        }

        // Validation du type
        $validTypes = ['CDI', 'CDD', 'Stage', 'Alternance', 'Autre'];
        if (!in_array($data['type'], $validTypes, true)) {
            $errors['type'] = 'Le type de contrat n\'est pas valide.';
        }

        // Date de début obligatoire
        if (empty($data['startDate'])) {
            $errors['startDate'] = 'La date de début est obligatoire.';
        } else {
            $startDate = $data['startDate'] instanceof \DateTimeInterface
                ? $data['startDate']
                : (is_string($data['startDate']) ? new \DateTime($data['startDate']) : null);

            if (!$startDate) {
                $errors['startDate'] = 'La date de début n\'est pas valide.';
            }
        }

        // Date de fin obligatoire pour CDD
        if ($data['type'] === 'CDD' && empty($data['endDate'])) {
            $errors['endDate'] = 'La date de fin est obligatoire pour un CDD.';
        }

        // Cohérence des dates
        if (!empty($data['startDate']) && !empty($data['endDate'])) {
            $startDate = $data['startDate'] instanceof \DateTimeInterface
                ? $data['startDate']
                : new \DateTime($data['startDate']);
            $endDate = $data['endDate'] instanceof \DateTimeInterface
                ? $data['endDate']
                : new \DateTime($data['endDate']);

            if ($endDate <= $startDate) {
                $errors['endDate'] = 'La date de fin doit être postérieure à la date de début.';
            }
        }

        // Salaire obligatoire
        if (empty($data['baseSalary'])) {
            $errors['baseSalary'] = 'Le salaire est obligatoire.';
        } elseif (!is_numeric($data['baseSalary']) || $data['baseSalary'] <= 0) {
            $errors['baseSalary'] = 'Le salaire doit être un nombre positif.';
        }

        return $errors;
    }

    /**
     * Valide les fichiers uploadés
     * @param array<string, UploadedFile|null> $files Les fichiers par type de document
     * @param bool $documentsRequired Si les documents sont obligatoires
     * @return array<string, string> Les erreurs par type de document
     */
    public function validateDocumentFiles(array $files, bool $documentsRequired = true): array
    {
        $errors = [];
        $requiredTypes = ['cni', 'rib', 'domicile', 'honorabilite'];

        foreach ($files as $type => $file) {
            if ($file === null) {
                if ($documentsRequired && in_array($type, $requiredTypes, true)) {
                    $errors[$type] = sprintf('Le document %s est obligatoire.', $this->getDocumentTypeLabel($type));
                }
                continue;
            }

            if (!$file instanceof UploadedFile) {
                continue;
            }

            // Vérification de la validité du fichier
            if (!$file->isValid()) {
                $errors[$type] = sprintf('Erreur lors de l\'upload du document %s.', $this->getDocumentTypeLabel($type));
                continue;
            }

            // Vérification du type MIME
            $mimeType = $file->getMimeType();
            if (!in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
                $errors[$type] = sprintf(
                    'Le document %s doit être au format PDF, JPEG ou PNG.',
                    $this->getDocumentTypeLabel($type)
                );
                continue;
            }

            // Vérification de la taille
            if ($file->getSize() > self::MAX_FILE_SIZE) {
                $errors[$type] = sprintf(
                    'Le document %s ne doit pas dépasser 10 Mo.',
                    $this->getDocumentTypeLabel($type)
                );
            }
        }

        // Vérification des documents obligatoires manquants
        if ($documentsRequired) {
            foreach ($requiredTypes as $requiredType) {
                if (!isset($files[$requiredType]) && !isset($errors[$requiredType])) {
                    $errors[$requiredType] = sprintf(
                        'Le document %s est obligatoire.',
                        $this->getDocumentTypeLabel($requiredType)
                    );
                }
            }
        }

        return $errors;
    }

    /**
     * Valide les options d'activation
     * @return array<string, string> Les erreurs
     */
    public function validateActivationOptions(string $activationMode, ?string $temporaryPassword): array
    {
        $errors = [];

        $validModes = ['email', 'password', 'none'];
        if (!in_array($activationMode, $validModes, true)) {
            $errors['activationMode'] = 'Le mode d\'activation n\'est pas valide.';
            return $errors;
        }

        if ($activationMode === 'password') {
            if (empty($temporaryPassword)) {
                $errors['temporaryPassword'] = 'Le mot de passe temporaire est obligatoire.';
            } elseif (strlen($temporaryPassword) < 8) {
                $errors['temporaryPassword'] = 'Le mot de passe doit contenir au moins 8 caractères.';
            }
        }

        return $errors;
    }

    /**
     * Valide l'ensemble des données de création
     * @return array{
     *     valid: bool,
     *     errors: array<string, array<string, string>>
     * }
     */
    public function validateAll(
        array $personalData,
        array $documentFiles,
        array $contractData,
        string $activationMode,
        ?string $temporaryPassword,
        bool $documentsRequired = true
    ): array {
        $errors = [];

        $personalErrors = $this->validatePersonalData($personalData);
        if (!empty($personalErrors)) {
            $errors['personal'] = $personalErrors;
        }

        $documentErrors = $this->validateDocumentFiles($documentFiles, $documentsRequired);
        if (!empty($documentErrors)) {
            $errors['documents'] = $documentErrors;
        }

        if (!empty($contractData['type'])) {
            $contractErrors = $this->validateContractData($contractData);
            if (!empty($contractErrors)) {
                $errors['contract'] = $contractErrors;
            }
        }

        $activationErrors = $this->validateActivationOptions($activationMode, $temporaryPassword);
        if (!empty($activationErrors)) {
            $errors['activation'] = $activationErrors;
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    private function isValidIban(string $iban): bool
    {
        $iban = strtoupper(str_replace(' ', '', $iban));

        if (!preg_match('/^[A-Z]{2}[0-9]{2}[A-Z0-9]+$/', $iban)) {
            return false;
        }

        // Validation basique de la longueur pour les pays courants
        $lengths = [
            'FR' => 27, 'DE' => 22, 'ES' => 24, 'IT' => 27, 'BE' => 16,
            'CH' => 21, 'GB' => 22, 'LU' => 20, 'NL' => 18, 'PT' => 25,
        ];

        $countryCode = substr($iban, 0, 2);
        if (isset($lengths[$countryCode]) && strlen($iban) !== $lengths[$countryCode]) {
            return false;
        }

        return true;
    }

    private function isValidBic(string $bic): bool
    {
        $bic = strtoupper(str_replace(' ', '', $bic));

        // BIC: 8 ou 11 caractères (BBBBCCLL ou BBBBCCLLXXX)
        return (bool) preg_match('/^[A-Z]{4}[A-Z]{2}[A-Z0-9]{2}([A-Z0-9]{3})?$/', $bic);
    }

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
