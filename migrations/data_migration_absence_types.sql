-- ========================================
-- SCRIPT DE MIGRATION DES DONNÉES EXISTANTES
-- Module Absence et Congés - Version 3.0
-- Date: 2025-11-26
-- ========================================

-- Étape 1: Créer les TypeAbsence depuis les types string existants
-- ========================================
INSERT IGNORE INTO type_absence (code, label, affects_planning, deduct_from_counter, requires_justification, justification_deadline_days, document_type, active, created_at, updated_at)
SELECT DISTINCT
    absence.type AS code,
    absence.type AS label,
    FALSE AS affects_planning,
    FALSE AS deduct_from_counter,
    FALSE AS requires_justification,
    NULL AS justification_deadline_days,
    NULL AS document_type,
    TRUE AS active,
    NOW() AS created_at,
    NOW() AS updated_at
FROM absence
WHERE absence.type IS NOT NULL
AND absence.type NOT IN ('CP', 'RTT', 'MAL', 'AT', 'CPSS', 'ABSAUT', 'ABSNJ');

-- Étape 2: Lier les absences existantes aux TypeAbsence créés
-- ========================================
UPDATE absence
INNER JOIN type_absence ON absence.type = type_absence.code
SET absence.absence_type_id = type_absence.id
WHERE absence.type IS NOT NULL;

-- Étape 3: Initialiser le statut justificatif pour toutes les absences
-- ========================================
UPDATE absence
SET justification_status = 'not_required'
WHERE justification_status IS NULL;

-- Étape 4: Vérifications de sécurité
-- ========================================

-- Vérification 1: Compter les absences sans type_absence_id
SELECT
    COUNT(*) AS absences_sans_type,
    'ATTENTION: Ces absences n\'ont pas de type_absence_id assigné' AS message
FROM absence
WHERE absence_type_id IS NULL;

-- Vérification 2: Compter les types créés
SELECT
    COUNT(*) AS types_crees,
    'Types d\'absence créés au total' AS message
FROM type_absence;

-- Vérification 3: Statistiques par type
SELECT
    t.code,
    t.label,
    COUNT(a.id) AS nombre_absences
FROM type_absence t
LEFT JOIN absence a ON t.id = a.absence_type_id
GROUP BY t.id, t.code, t.label
ORDER BY COUNT(a.id) DESC;

-- Étape 5 (OPTIONNELLE - À FAIRE MANUELLEMENT APRÈS VÉRIFICATION):
-- Supprimer l'ancienne colonne 'type' une fois la migration validée
-- ========================================
-- ALTER TABLE absence DROP COLUMN type;

-- ========================================
-- FIN DU SCRIPT
-- ========================================
-- IMPORTANT: Exécuter les vérifications avant de supprimer la colonne 'type'
-- IMPORTANT: Faire un backup de la base avant d'exécuter ce script
