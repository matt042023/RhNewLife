<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration pour le module Paie & Éléments Variables
 * - Création des tables consolidation_paie, consolidation_paie_history, compteur_cp
 * - Modification de la table element_variable (ajout category, status, consolidation_id, validated_by_id, validated_at)
 */
final class Version20260117100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Module Paie: création ConsolidationPaie, ConsolidationPaieHistory, CompteurCP et modification ElementVariable';
    }

    public function up(Schema $schema): void
    {
        // Table consolidation_paie
        $this->addSql('CREATE TABLE consolidation_paie (
            id INT AUTO_INCREMENT NOT NULL,
            user_id INT NOT NULL,
            validated_by_id INT DEFAULT NULL,
            period VARCHAR(7) NOT NULL,
            status VARCHAR(20) DEFAULT \'draft\' NOT NULL,
            jours_travailes NUMERIC(5, 2) DEFAULT \'0.00\' NOT NULL,
            jours_evenements NUMERIC(5, 2) DEFAULT \'0.00\' NOT NULL,
            jours_absence JSON DEFAULT NULL,
            cp_acquis NUMERIC(5, 2) DEFAULT \'0.00\' NOT NULL,
            cp_pris NUMERIC(5, 2) DEFAULT \'0.00\' NOT NULL,
            cp_solde_debut NUMERIC(5, 2) DEFAULT \'0.00\' NOT NULL,
            cp_solde_fin NUMERIC(5, 2) DEFAULT \'0.00\' NOT NULL,
            total_variables NUMERIC(10, 2) DEFAULT \'0.00\' NOT NULL,
            validated_at DATETIME DEFAULT NULL,
            exported_at DATETIME DEFAULT NULL,
            sent_to_accountant_at DATETIME DEFAULT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            INDEX IDX_CONSOLIDATION_USER (user_id),
            INDEX IDX_CONSOLIDATION_VALIDATED_BY (validated_by_id),
            UNIQUE INDEX unique_user_period (user_id, period),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE consolidation_paie ADD CONSTRAINT FK_CONSOLIDATION_USER FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE consolidation_paie ADD CONSTRAINT FK_CONSOLIDATION_VALIDATED_BY FOREIGN KEY (validated_by_id) REFERENCES user (id) ON DELETE SET NULL');

        // Table consolidation_paie_history
        $this->addSql('CREATE TABLE consolidation_paie_history (
            id INT AUTO_INCREMENT NOT NULL,
            consolidation_id INT NOT NULL,
            modified_by_id INT NOT NULL,
            action VARCHAR(50) NOT NULL,
            field VARCHAR(100) DEFAULT NULL,
            old_value LONGTEXT DEFAULT NULL,
            new_value LONGTEXT DEFAULT NULL,
            modified_at DATETIME NOT NULL,
            comment LONGTEXT DEFAULT NULL,
            INDEX IDX_HISTORY_CONSOLIDATION (consolidation_id),
            INDEX IDX_HISTORY_MODIFIED_BY (modified_by_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE consolidation_paie_history ADD CONSTRAINT FK_HISTORY_CONSOLIDATION FOREIGN KEY (consolidation_id) REFERENCES consolidation_paie (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE consolidation_paie_history ADD CONSTRAINT FK_HISTORY_MODIFIED_BY FOREIGN KEY (modified_by_id) REFERENCES user (id) ON DELETE CASCADE');

        // Table compteur_cp
        $this->addSql('CREATE TABLE compteur_cp (
            id INT AUTO_INCREMENT NOT NULL,
            user_id INT NOT NULL,
            periode_reference VARCHAR(9) NOT NULL,
            solde_initial NUMERIC(5, 2) DEFAULT \'0.00\' NOT NULL,
            acquis NUMERIC(5, 2) DEFAULT \'0.00\' NOT NULL,
            pris NUMERIC(5, 2) DEFAULT \'0.00\' NOT NULL,
            ajustement_admin NUMERIC(5, 2) DEFAULT \'0.00\' NOT NULL,
            ajustement_comment LONGTEXT DEFAULT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            INDEX IDX_COMPTEUR_CP_USER (user_id),
            UNIQUE INDEX unique_user_periode (user_id, periode_reference),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE compteur_cp ADD CONSTRAINT FK_COMPTEUR_CP_USER FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');

        // Modification de element_variable
        $this->addSql('ALTER TABLE element_variable ADD category VARCHAR(50) DEFAULT \'prime\' NOT NULL');
        $this->addSql('ALTER TABLE element_variable ADD status VARCHAR(20) DEFAULT \'draft\' NOT NULL');
        $this->addSql('ALTER TABLE element_variable ADD consolidation_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE element_variable ADD validated_by_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE element_variable ADD validated_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE element_variable ADD CONSTRAINT FK_ELEMENT_CONSOLIDATION FOREIGN KEY (consolidation_id) REFERENCES consolidation_paie (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE element_variable ADD CONSTRAINT FK_ELEMENT_VALIDATED_BY FOREIGN KEY (validated_by_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_ELEMENT_CONSOLIDATION ON element_variable (consolidation_id)');
        $this->addSql('CREATE INDEX IDX_ELEMENT_VALIDATED_BY ON element_variable (validated_by_id)');
    }

    public function down(Schema $schema): void
    {
        // Suppression des contraintes et colonnes de element_variable
        $this->addSql('ALTER TABLE element_variable DROP FOREIGN KEY FK_ELEMENT_CONSOLIDATION');
        $this->addSql('ALTER TABLE element_variable DROP FOREIGN KEY FK_ELEMENT_VALIDATED_BY');
        $this->addSql('DROP INDEX IDX_ELEMENT_CONSOLIDATION ON element_variable');
        $this->addSql('DROP INDEX IDX_ELEMENT_VALIDATED_BY ON element_variable');
        $this->addSql('ALTER TABLE element_variable DROP category');
        $this->addSql('ALTER TABLE element_variable DROP status');
        $this->addSql('ALTER TABLE element_variable DROP consolidation_id');
        $this->addSql('ALTER TABLE element_variable DROP validated_by_id');
        $this->addSql('ALTER TABLE element_variable DROP validated_at');

        // Suppression des tables
        $this->addSql('ALTER TABLE compteur_cp DROP FOREIGN KEY FK_COMPTEUR_CP_USER');
        $this->addSql('DROP TABLE compteur_cp');

        $this->addSql('ALTER TABLE consolidation_paie_history DROP FOREIGN KEY FK_HISTORY_CONSOLIDATION');
        $this->addSql('ALTER TABLE consolidation_paie_history DROP FOREIGN KEY FK_HISTORY_MODIFIED_BY');
        $this->addSql('DROP TABLE consolidation_paie_history');

        $this->addSql('ALTER TABLE consolidation_paie DROP FOREIGN KEY FK_CONSOLIDATION_USER');
        $this->addSql('ALTER TABLE consolidation_paie DROP FOREIGN KEY FK_CONSOLIDATION_VALIDATED_BY');
        $this->addSql('DROP TABLE consolidation_paie');
    }
}
