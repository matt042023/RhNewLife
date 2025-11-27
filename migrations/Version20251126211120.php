<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251126211120 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE compteur_absence (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, absence_type_id INT NOT NULL, year INT NOT NULL, earned DOUBLE PRECISION DEFAULT \'0\' NOT NULL, taken DOUBLE PRECISION DEFAULT \'0\' NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_C49D8C0BA76ED395 (user_id), INDEX IDX_C49D8C0BCCAA91B (absence_type_id), UNIQUE INDEX unique_user_type_year (user_id, absence_type_id, year), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE type_absence (id INT AUTO_INCREMENT NOT NULL, code VARCHAR(20) NOT NULL, label VARCHAR(100) NOT NULL, affects_planning TINYINT(1) DEFAULT 0 NOT NULL, deduct_from_counter TINYINT(1) DEFAULT 0 NOT NULL, requires_justification TINYINT(1) DEFAULT 0 NOT NULL, justification_deadline_days INT DEFAULT NULL, document_type VARCHAR(50) DEFAULT NULL, active TINYINT(1) DEFAULT 1 NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_5E7B8F3C77153098 (code), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE compteur_absence ADD CONSTRAINT FK_C49D8C0BA76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE compteur_absence ADD CONSTRAINT FK_C49D8C0BCCAA91B FOREIGN KEY (absence_type_id) REFERENCES type_absence (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE absence ADD absence_type_id INT DEFAULT NULL, ADD validated_by_id INT DEFAULT NULL, ADD justification_status VARCHAR(30) DEFAULT NULL, ADD justification_deadline DATETIME DEFAULT NULL, ADD rejection_reason LONGTEXT DEFAULT NULL, ADD admin_comment LONGTEXT DEFAULT NULL, ADD affects_planning TINYINT(1) DEFAULT 0 NOT NULL, ADD working_days_count DOUBLE PRECISION DEFAULT NULL, CHANGE type type VARCHAR(50) DEFAULT NULL');
        $this->addSql('ALTER TABLE absence ADD CONSTRAINT FK_765AE0C9CCAA91B FOREIGN KEY (absence_type_id) REFERENCES type_absence (id)');
        $this->addSql('ALTER TABLE absence ADD CONSTRAINT FK_765AE0C9C69DE5E5 FOREIGN KEY (validated_by_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_765AE0C9CCAA91B ON absence (absence_type_id)');
        $this->addSql('CREATE INDEX IDX_765AE0C9C69DE5E5 ON absence (validated_by_id)');
        $this->addSql('ALTER TABLE contract RENAME INDEX uniq_contract_signature_token TO UNIQ_E98F2859D7605360');
        $this->addSql('ALTER TABLE contract RENAME INDEX idx_contract_template TO IDX_E98F28595DA0FB8');
        $this->addSql('ALTER TABLE contract RENAME INDEX idx_contract_validated_by TO IDX_E98F2859C69DE5E5');
        $this->addSql('ALTER TABLE document ADD rejected_at DATETIME DEFAULT NULL, ADD rejection_reason LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE template_contrat RENAME INDEX idx_template_contrat_created_by TO IDX_338CE2AAB03A8386');
        $this->addSql('ALTER TABLE template_contrat RENAME INDEX idx_template_contrat_modified_by TO IDX_338CE2AA99049ECE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE absence DROP FOREIGN KEY FK_765AE0C9CCAA91B');
        $this->addSql('ALTER TABLE compteur_absence DROP FOREIGN KEY FK_C49D8C0BA76ED395');
        $this->addSql('ALTER TABLE compteur_absence DROP FOREIGN KEY FK_C49D8C0BCCAA91B');
        $this->addSql('DROP TABLE compteur_absence');
        $this->addSql('DROP TABLE type_absence');
        $this->addSql('ALTER TABLE absence DROP FOREIGN KEY FK_765AE0C9C69DE5E5');
        $this->addSql('DROP INDEX IDX_765AE0C9CCAA91B ON absence');
        $this->addSql('DROP INDEX IDX_765AE0C9C69DE5E5 ON absence');
        $this->addSql('ALTER TABLE absence DROP absence_type_id, DROP validated_by_id, DROP justification_status, DROP justification_deadline, DROP rejection_reason, DROP admin_comment, DROP affects_planning, DROP working_days_count, CHANGE type type VARCHAR(50) NOT NULL');
        $this->addSql('ALTER TABLE contract RENAME INDEX idx_e98f2859c69de5e5 TO IDX_CONTRACT_VALIDATED_BY');
        $this->addSql('ALTER TABLE contract RENAME INDEX uniq_e98f2859d7605360 TO UNIQ_CONTRACT_SIGNATURE_TOKEN');
        $this->addSql('ALTER TABLE contract RENAME INDEX idx_e98f28595da0fb8 TO IDX_CONTRACT_TEMPLATE');
        $this->addSql('ALTER TABLE document DROP rejected_at, DROP rejection_reason');
        $this->addSql('ALTER TABLE template_contrat RENAME INDEX idx_338ce2aa99049ece TO IDX_TEMPLATE_CONTRAT_MODIFIED_BY');
        $this->addSql('ALTER TABLE template_contrat RENAME INDEX idx_338ce2aab03a8386 TO IDX_TEMPLATE_CONTRAT_CREATED_BY');
    }
}
