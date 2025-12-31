<?php

namespace App\Service\SqueletteGarde;

use App\Repository\SqueletteGardeRepository;

class SqueletteGardeValidator
{
    // Validation error types
    public const ERROR_EMPTY_NAME = 'empty_name';
    public const ERROR_DUPLICATE_NAME = 'duplicate_name';
    public const ERROR_INVALID_CONFIG = 'invalid_config';
    public const ERROR_INVALID_DAY = 'invalid_day';
    public const ERROR_INVALID_TIME = 'invalid_time';
    public const ERROR_INVALID_DURATION = 'invalid_duration';

    // Warning types (non-blocking)
    public const WARNING_NO_CRENEAUX = 'no_creneaux';
    public const WARNING_OVERLAP = 'overlap';
    public const WARNING_LARGE_CONFIG = 'large_config';

    private array $errors = [];
    private array $warnings = [];

    public function __construct(
        private SqueletteGardeRepository $repository
    ) {}

    /**
     * Validate template name
     */
    public function validateName(string $nom, ?int $excludeId = null): void
    {
        $this->errors = [];

        if (trim($nom) === '') {
            $this->addError(self::ERROR_EMPTY_NAME, 'Le nom ne peut pas être vide.');
        }

        if ($this->repository->nameExists($nom, $excludeId)) {
            $this->addError(self::ERROR_DUPLICATE_NAME,
                "Un template avec le nom '{$nom}' existe déjà.");
        }

        if (!empty($this->errors)) {
            throw new SqueletteGardeValidationException($this->errors);
        }
    }

    /**
     * Validate configuration structure
     */
    public function validateConfiguration(array $config): array
    {
        $this->errors = [];
        $this->warnings = [];

        // Check structure
        if (!isset($config['creneaux_garde']) || !is_array($config['creneaux_garde'])) {
            $config['creneaux_garde'] = [];
        }
        if (!isset($config['creneaux_renfort']) || !is_array($config['creneaux_renfort'])) {
            $config['creneaux_renfort'] = [];
        }

        // Validate creneaux_garde
        foreach ($config['creneaux_garde'] as $index => $creneau) {
            $this->validateCreneauGarde($creneau, $index);
        }

        // Validate creneaux_renfort
        foreach ($config['creneaux_renfort'] as $index => $creneau) {
            $this->validateCreneauRenfort($creneau, $index);
        }

        // Warnings
        if (empty($config['creneaux_garde']) && empty($config['creneaux_renfort'])) {
            $this->addWarning(self::WARNING_NO_CRENEAUX,
                'Le template ne contient aucun créneau.');
        }

        // Check JSON size (TEXT field max ~64KB)
        $jsonSize = strlen(json_encode($config));
        if ($jsonSize > 60000) {
            $this->addWarning(self::WARNING_LARGE_CONFIG,
                "Configuration très volumineuse ({$jsonSize} bytes). Limite: 64KB.");
        }

        if (!empty($this->errors)) {
            throw new SqueletteGardeValidationException($this->errors, $this->warnings);
        }

        return $this->warnings;
    }

    /**
     * Validate a single creneau de garde
     */
    private function validateCreneauGarde(array $creneau, int $index): void
    {
        $prefix = "Créneau garde #{$index}";

        // Required fields
        $required = ['jour_debut', 'heure_debut', 'duree_heures'];
        foreach ($required as $field) {
            if (!isset($creneau[$field])) {
                $this->addError(self::ERROR_INVALID_CONFIG,
                    "{$prefix}: champ '{$field}' manquant.");
            }
        }

        // Validate type
        if (isset($creneau['type']) && !in_array($creneau['type'], ['garde_24h', 'garde_48h', 'garde_custom'])) {
            $this->addError(self::ERROR_INVALID_CONFIG,
                "{$prefix}: type invalide '{$creneau['type']}'.");
        }

        // Validate days (1-7)
        if (isset($creneau['jour_debut']) && ($creneau['jour_debut'] < 1 || $creneau['jour_debut'] > 7)) {
            $this->addError(self::ERROR_INVALID_DAY,
                "{$prefix}: jour_debut doit être entre 1 et 7.");
        }

        // Validate hours (0-23)
        if (isset($creneau['heure_debut']) && ($creneau['heure_debut'] < 0 || $creneau['heure_debut'] > 23)) {
            $this->addError(self::ERROR_INVALID_TIME,
                "{$prefix}: heure_debut doit être entre 0 et 23.");
        }

        // Validate duration
        if (isset($creneau['duree_heures'])) {
            if ($creneau['duree_heures'] < 1 || $creneau['duree_heures'] > 168) {
                $this->addError(self::ERROR_INVALID_DURATION,
                    "{$prefix}: durée doit être entre 1h et 168h.");
            }

            // Type-specific validation (warnings only)
            if (isset($creneau['type'])) {
                if ($creneau['type'] === 'garde_24h' && abs($creneau['duree_heures'] - 24) > 1) {
                    $this->addWarning(self::WARNING_OVERLAP,
                        "{$prefix}: durée de {$creneau['duree_heures']}h ne correspond pas à garde_24h.");
                }
                if ($creneau['type'] === 'garde_48h' && abs($creneau['duree_heures'] - 48) > 1) {
                    $this->addWarning(self::WARNING_OVERLAP,
                        "{$prefix}: durée de {$creneau['duree_heures']}h ne correspond pas à garde_48h.");
                }
            }
        }
    }

    /**
     * Validate a single creneau de renfort
     */
    private function validateCreneauRenfort(array $creneau, int $index): void
    {
        $prefix = "Créneau renfort #{$index}";

        // Required fields
        $required = ['jour', 'heure_debut', 'heure_fin'];
        foreach ($required as $field) {
            if (!isset($creneau[$field])) {
                $this->addError(self::ERROR_INVALID_CONFIG,
                    "{$prefix}: champ '{$field}' manquant.");
            }
        }

        // Validate day
        if (isset($creneau['jour']) && ($creneau['jour'] < 1 || $creneau['jour'] > 7)) {
            $this->addError(self::ERROR_INVALID_DAY,
                "{$prefix}: jour doit être entre 1 et 7.");
        }

        // Validate hours
        if (isset($creneau['heure_debut']) && ($creneau['heure_debut'] < 0 || $creneau['heure_debut'] > 23)) {
            $this->addError(self::ERROR_INVALID_TIME,
                "{$prefix}: heure_debut doit être entre 0 et 23.");
        }
        if (isset($creneau['heure_fin']) && ($creneau['heure_fin'] < 0 || $creneau['heure_fin'] > 23)) {
            $this->addError(self::ERROR_INVALID_TIME,
                "{$prefix}: heure_fin doit être entre 0 et 23.");
        }

        // Validate same-day duration
        if (isset($creneau['heure_debut'], $creneau['heure_fin'])) {
            if ($creneau['heure_fin'] <= $creneau['heure_debut']) {
                $this->addError(self::ERROR_INVALID_DURATION,
                    "{$prefix}: heure_fin doit être après heure_debut.");
            }
        }
    }

    private function addError(string $type, string $message): void
    {
        $this->errors[] = ['type' => $type, 'message' => $message];
    }

    private function addWarning(string $type, string $message): void
    {
        $this->warnings[] = ['type' => $type, 'message' => $message];
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getWarnings(): array
    {
        return $this->warnings;
    }
}
