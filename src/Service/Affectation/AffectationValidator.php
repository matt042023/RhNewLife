<?php

namespace App\Service\Affectation;

use App\Entity\Affectation;

/**
 * Service de validation métier pour les affectations
 *
 * Valide les règles métier spécifiques aux types d'affectation:
 * - Gardes principales (24h/48h) doivent avoir une villa
 * - Renforts peuvent avoir une villa (villa-spécifique) ou non (centre-complet)
 */
class AffectationValidator
{
    /**
     * Valide l'assignation de villa selon le type d'affectation
     *
     * @param Affectation $affectation
     * @return array{errors: string[], warnings: string[]}
     */
    public function validateVillaAssignment(Affectation $affectation): array
    {
        $errors = [];
        $warnings = [];

        $type = $affectation->getType();
        $villa = $affectation->getVilla();

        // Garde principale DOIT avoir une villa
        if (in_array($type, [Affectation::TYPE_GARDE_24H, Affectation::TYPE_GARDE_48H])) {
            if (!$villa) {
                $errors[] = "Les gardes principales (24h/48h) doivent être liées à une villa";
            }
        }

        // Renfort: RECOMMANDÉ sans villa, mais autorisé (compatibilité + renforts villa-spécifiques)
        if ($type === Affectation::TYPE_RENFORT) {
            if ($villa) {
                $warnings[] = sprintf(
                    "Ce renfort est lié à la villa '%s'. Pour un renfort centre-complet, ne pas assigner de villa.",
                    $villa->getNom()
                );
                // Note: Ne pas forcer villa = null, on permet les renforts villa-spécifiques
            }
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    /**
     * Valide une affectation complète (toutes les règles métier)
     *
     * @param Affectation $affectation
     * @return array{errors: string[], warnings: string[]}
     */
    public function validate(Affectation $affectation): array
    {
        $errors = [];
        $warnings = [];

        // Validation villa
        $villaValidation = $this->validateVillaAssignment($affectation);
        $errors = array_merge($errors, $villaValidation['errors']);
        $warnings = array_merge($warnings, $villaValidation['warnings']);

        // Autres validations métier peuvent être ajoutées ici
        // - Validation des dates
        // - Validation utilisateur assigné
        // - Validation conflits de planning
        // etc.

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    /**
     * Vérifie si une affectation est valide
     *
     * @param Affectation $affectation
     * @return bool
     */
    public function isValid(Affectation $affectation): bool
    {
        $validation = $this->validate($affectation);
        return empty($validation['errors']);
    }
}
