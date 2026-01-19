<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260118173851 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE compteur_cp (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, periode_reference VARCHAR(9) NOT NULL, solde_initial NUMERIC(5, 2) DEFAULT \'0\' NOT NULL, acquis NUMERIC(5, 2) DEFAULT \'0\' NOT NULL, pris NUMERIC(5, 2) DEFAULT \'0\' NOT NULL, ajustement_admin NUMERIC(5, 2) DEFAULT \'0\' NOT NULL, ajustement_comment LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_4A0EA4E7A76ED395 (user_id), UNIQUE INDEX unique_user_periode (user_id, periode_reference), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE consolidation_paie (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, validated_by_id INT DEFAULT NULL, period VARCHAR(7) NOT NULL, status VARCHAR(20) DEFAULT \'draft\' NOT NULL, jours_travailes NUMERIC(5, 2) DEFAULT \'0\' NOT NULL, jours_evenements NUMERIC(5, 2) DEFAULT \'0\' NOT NULL, jours_absence JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', cp_acquis NUMERIC(5, 2) DEFAULT \'0\' NOT NULL, cp_pris NUMERIC(5, 2) DEFAULT \'0\' NOT NULL, cp_solde_debut NUMERIC(5, 2) DEFAULT \'0\' NOT NULL, cp_solde_fin NUMERIC(5, 2) DEFAULT \'0\' NOT NULL, total_variables NUMERIC(10, 2) DEFAULT \'0\' NOT NULL, validated_at DATETIME DEFAULT NULL, exported_at DATETIME DEFAULT NULL, sent_to_accountant_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_C865968DA76ED395 (user_id), INDEX IDX_C865968DC69DE5E5 (validated_by_id), UNIQUE INDEX unique_user_period (user_id, period), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE consolidation_paie_history (id INT AUTO_INCREMENT NOT NULL, consolidation_id INT NOT NULL, modified_by_id INT NOT NULL, action VARCHAR(50) NOT NULL, field VARCHAR(100) DEFAULT NULL, old_value LONGTEXT DEFAULT NULL, new_value LONGTEXT DEFAULT NULL, modified_at DATETIME NOT NULL, comment LONGTEXT DEFAULT NULL, INDEX IDX_96887FC8F48F4028 (consolidation_id), INDEX IDX_96887FC899049ECE (modified_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE compteur_cp ADD CONSTRAINT FK_4A0EA4E7A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE consolidation_paie ADD CONSTRAINT FK_C865968DA76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE consolidation_paie ADD CONSTRAINT FK_C865968DC69DE5E5 FOREIGN KEY (validated_by_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE consolidation_paie_history ADD CONSTRAINT FK_96887FC8F48F4028 FOREIGN KEY (consolidation_id) REFERENCES consolidation_paie (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE consolidation_paie_history ADD CONSTRAINT FK_96887FC899049ECE FOREIGN KEY (modified_by_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE element_variable ADD consolidation_id INT DEFAULT NULL, ADD validated_by_id INT DEFAULT NULL, ADD category VARCHAR(50) DEFAULT \'prime\' NOT NULL, ADD status VARCHAR(20) DEFAULT \'draft\' NOT NULL, ADD validated_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE element_variable ADD CONSTRAINT FK_A0BCF920F48F4028 FOREIGN KEY (consolidation_id) REFERENCES consolidation_paie (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE element_variable ADD CONSTRAINT FK_A0BCF920C69DE5E5 FOREIGN KEY (validated_by_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_A0BCF920F48F4028 ON element_variable (consolidation_id)');
        $this->addSql('CREATE INDEX IDX_A0BCF920C69DE5E5 ON element_variable (validated_by_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE element_variable DROP FOREIGN KEY FK_A0BCF920F48F4028');
        $this->addSql('ALTER TABLE compteur_cp DROP FOREIGN KEY FK_4A0EA4E7A76ED395');
        $this->addSql('ALTER TABLE consolidation_paie DROP FOREIGN KEY FK_C865968DA76ED395');
        $this->addSql('ALTER TABLE consolidation_paie DROP FOREIGN KEY FK_C865968DC69DE5E5');
        $this->addSql('ALTER TABLE consolidation_paie_history DROP FOREIGN KEY FK_96887FC8F48F4028');
        $this->addSql('ALTER TABLE consolidation_paie_history DROP FOREIGN KEY FK_96887FC899049ECE');
        $this->addSql('DROP TABLE compteur_cp');
        $this->addSql('DROP TABLE consolidation_paie');
        $this->addSql('DROP TABLE consolidation_paie_history');
        $this->addSql('ALTER TABLE element_variable DROP FOREIGN KEY FK_A0BCF920C69DE5E5');
        $this->addSql('DROP INDEX IDX_A0BCF920F48F4028 ON element_variable');
        $this->addSql('DROP INDEX IDX_A0BCF920C69DE5E5 ON element_variable');
        $this->addSql('ALTER TABLE element_variable DROP consolidation_id, DROP validated_by_id, DROP category, DROP status, DROP validated_at');
    }
}
