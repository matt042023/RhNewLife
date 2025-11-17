<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration WF09 - Gestion complète des contrats
 * - Création table template_contrat
 * - Ajout champs dans contract pour workflow signature
 * - Migration statuts existants vers nouveaux statuts WF09
 */
final class Version20251114150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'WF09: Add TemplateContrat entity and update Contract entity with signature workflow fields';
    }

    public function up(Schema $schema): void
    {
        // Création table template_contrat
        $this->addSql('CREATE TABLE template_contrat (
            id INT AUTO_INCREMENT NOT NULL,
            created_by_id INT DEFAULT NULL,
            modified_by_id INT DEFAULT NULL,
            name VARCHAR(255) NOT NULL,
            description LONGTEXT DEFAULT NULL,
            content_html LONGTEXT NOT NULL,
            active TINYINT(1) DEFAULT 1 NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            INDEX IDX_TEMPLATE_CONTRAT_CREATED_BY (created_by_id),
            INDEX IDX_TEMPLATE_CONTRAT_MODIFIED_BY (modified_by_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE template_contrat
            ADD CONSTRAINT FK_TEMPLATE_CONTRAT_CREATED_BY
            FOREIGN KEY (created_by_id) REFERENCES user (id) ON DELETE SET NULL');

        $this->addSql('ALTER TABLE template_contrat
            ADD CONSTRAINT FK_TEMPLATE_CONTRAT_MODIFIED_BY
            FOREIGN KEY (modified_by_id) REFERENCES user (id) ON DELETE SET NULL');

        // Ajout des nouveaux champs dans contract
        $this->addSql('ALTER TABLE contract
            ADD template_id INT DEFAULT NULL,
            ADD draft_file_url VARCHAR(500) DEFAULT NULL,
            ADD signed_file_url VARCHAR(500) DEFAULT NULL,
            ADD signature_token VARCHAR(100) DEFAULT NULL,
            ADD token_expires_at DATETIME DEFAULT NULL,
            ADD validated_by_id INT DEFAULT NULL,
            ADD signature_ip VARCHAR(45) DEFAULT NULL,
            ADD signature_user_agent LONGTEXT DEFAULT NULL');

        // Ajout des index et contraintes
        $this->addSql('CREATE INDEX IDX_CONTRACT_TEMPLATE ON contract (template_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_CONTRACT_SIGNATURE_TOKEN ON contract (signature_token)');
        $this->addSql('CREATE INDEX IDX_CONTRACT_VALIDATED_BY ON contract (validated_by_id)');

        $this->addSql('ALTER TABLE contract
            ADD CONSTRAINT FK_CONTRACT_TEMPLATE
            FOREIGN KEY (template_id) REFERENCES template_contrat (id) ON DELETE SET NULL');

        $this->addSql('ALTER TABLE contract
            ADD CONSTRAINT FK_CONTRACT_VALIDATED_BY
            FOREIGN KEY (validated_by_id) REFERENCES user (id) ON DELETE SET NULL');

        // Migration des statuts existants vers nouveaux statuts WF09
        // Important: Exécuter AVANT de changer la longueur de la colonne
        $this->addSql("UPDATE contract SET status = 'DRAFT' WHERE status = 'draft'");
        $this->addSql("UPDATE contract SET status = 'ACTIVE' WHERE status = 'active'");
        $this->addSql("UPDATE contract SET status = 'SIGNED_PENDING_VALIDATION' WHERE status = 'signed'");
        $this->addSql("UPDATE contract SET status = 'SUSPENDED' WHERE status = 'suspended'");
        $this->addSql("UPDATE contract SET status = 'ARCHIVED' WHERE status = 'terminated'");

        // Augmenter la longueur de la colonne status pour nouveaux statuts plus longs
        $this->addSql('ALTER TABLE contract MODIFY status VARCHAR(50) DEFAULT \'DRAFT\' NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // Rollback: Migration inverse des statuts
        $this->addSql("UPDATE contract SET status = 'draft' WHERE status = 'DRAFT'");
        $this->addSql("UPDATE contract SET status = 'active' WHERE status = 'ACTIVE'");
        $this->addSql("UPDATE contract SET status = 'signed' WHERE status IN ('SIGNED_PENDING_VALIDATION', 'PENDING_SIGNATURE')");
        $this->addSql("UPDATE contract SET status = 'suspended' WHERE status = 'SUSPENDED'");
        $this->addSql("UPDATE contract SET status = 'terminated' WHERE status IN ('ARCHIVED', 'REPLACED', 'CANCELLED')");

        // Réduire la longueur de la colonne status
        $this->addSql('ALTER TABLE contract MODIFY status VARCHAR(20) DEFAULT \'draft\' NOT NULL');

        // Suppression des contraintes foreign key
        $this->addSql('ALTER TABLE contract DROP FOREIGN KEY FK_CONTRACT_TEMPLATE');
        $this->addSql('ALTER TABLE contract DROP FOREIGN KEY FK_CONTRACT_VALIDATED_BY');

        // Suppression des index
        $this->addSql('DROP INDEX IDX_CONTRACT_TEMPLATE ON contract');
        $this->addSql('DROP INDEX UNIQ_CONTRACT_SIGNATURE_TOKEN ON contract');
        $this->addSql('DROP INDEX IDX_CONTRACT_VALIDATED_BY ON contract');

        // Suppression des colonnes ajoutées
        $this->addSql('ALTER TABLE contract
            DROP template_id,
            DROP draft_file_url,
            DROP signed_file_url,
            DROP signature_token,
            DROP token_expires_at,
            DROP validated_by_id,
            DROP signature_ip,
            DROP signature_user_agent');

        // Suppression des contraintes de template_contrat
        $this->addSql('ALTER TABLE template_contrat DROP FOREIGN KEY FK_TEMPLATE_CONTRAT_CREATED_BY');
        $this->addSql('ALTER TABLE template_contrat DROP FOREIGN KEY FK_TEMPLATE_CONTRAT_MODIFIED_BY');

        // Suppression de la table template_contrat
        $this->addSql('DROP TABLE template_contrat');
    }
}
