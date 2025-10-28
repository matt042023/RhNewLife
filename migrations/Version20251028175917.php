<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251028175917 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE absence (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, type VARCHAR(50) NOT NULL, start_at DATETIME NOT NULL, end_at DATETIME NOT NULL, reason LONGTEXT DEFAULT NULL, status VARCHAR(30) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_765AE0C9A76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE element_variable (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, label VARCHAR(255) NOT NULL, amount NUMERIC(10, 2) NOT NULL, period VARCHAR(7) NOT NULL, description LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_A0BCF920A76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE formation (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, title VARCHAR(255) NOT NULL, organization VARCHAR(255) DEFAULT NULL, hours NUMERIC(5, 2) DEFAULT NULL, completed_at DATE DEFAULT NULL, renewal_at DATE DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_404021BFA76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE absence ADD CONSTRAINT FK_765AE0C9A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE element_variable ADD CONSTRAINT FK_A0BCF920A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE formation ADD CONSTRAINT FK_404021BFA76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE document ADD absence_id INT DEFAULT NULL, ADD formation_id INT DEFAULT NULL, ADD element_variable_id INT DEFAULT NULL, ADD version INT DEFAULT 1 NOT NULL, ADD archived TINYINT(1) DEFAULT 0 NOT NULL, ADD archived_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', ADD archive_reason LONGTEXT DEFAULT NULL, ADD retention_years INT DEFAULT NULL');
        $this->addSql('ALTER TABLE document ADD CONSTRAINT FK_D8698A762DFF238F FOREIGN KEY (absence_id) REFERENCES absence (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE document ADD CONSTRAINT FK_D8698A765200282E FOREIGN KEY (formation_id) REFERENCES formation (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE document ADD CONSTRAINT FK_D8698A7659A52623 FOREIGN KEY (element_variable_id) REFERENCES element_variable (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_D8698A762DFF238F ON document (absence_id)');
        $this->addSql('CREATE INDEX IDX_D8698A765200282E ON document (formation_id)');
        $this->addSql('CREATE INDEX IDX_D8698A7659A52623 ON document (element_variable_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE document DROP FOREIGN KEY FK_D8698A762DFF238F');
        $this->addSql('ALTER TABLE document DROP FOREIGN KEY FK_D8698A7659A52623');
        $this->addSql('ALTER TABLE document DROP FOREIGN KEY FK_D8698A765200282E');
        $this->addSql('ALTER TABLE absence DROP FOREIGN KEY FK_765AE0C9A76ED395');
        $this->addSql('ALTER TABLE element_variable DROP FOREIGN KEY FK_A0BCF920A76ED395');
        $this->addSql('ALTER TABLE formation DROP FOREIGN KEY FK_404021BFA76ED395');
        $this->addSql('DROP TABLE absence');
        $this->addSql('DROP TABLE element_variable');
        $this->addSql('DROP TABLE formation');
        $this->addSql('DROP INDEX IDX_D8698A762DFF238F ON document');
        $this->addSql('DROP INDEX IDX_D8698A765200282E ON document');
        $this->addSql('DROP INDEX IDX_D8698A7659A52623 ON document');
        $this->addSql('ALTER TABLE document DROP absence_id, DROP formation_id, DROP element_variable_id, DROP version, DROP archived, DROP archived_at, DROP archive_reason, DROP retention_years');
    }
}
