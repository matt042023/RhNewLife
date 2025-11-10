<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251110102421 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE absence (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, type VARCHAR(50) NOT NULL, start_at DATETIME NOT NULL, end_at DATETIME NOT NULL, reason LONGTEXT DEFAULT NULL, status VARCHAR(30) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_765AE0C9A76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE contract (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, parent_contract_id INT DEFAULT NULL, created_by_id INT DEFAULT NULL, type VARCHAR(50) NOT NULL, start_date DATE NOT NULL, end_date DATE DEFAULT NULL, essai_end_date DATE DEFAULT NULL, base_salary NUMERIC(10, 2) NOT NULL, prime NUMERIC(10, 2) DEFAULT NULL, working_days JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', villa VARCHAR(100) DEFAULT NULL, activity_rate NUMERIC(3, 2) DEFAULT \'1.00\' NOT NULL, weekly_hours NUMERIC(5, 2) DEFAULT NULL, mutuelle TINYINT(1) DEFAULT 0 NOT NULL, prevoyance TINYINT(1) DEFAULT 0 NOT NULL, status VARCHAR(20) DEFAULT \'draft\' NOT NULL, version INT DEFAULT 1 NOT NULL, termination_reason LONGTEXT DEFAULT NULL, notes LONGTEXT DEFAULT NULL, validated_at DATETIME DEFAULT NULL, signed_at DATETIME DEFAULT NULL, terminated_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_E98F2859A76ED395 (user_id), INDEX IDX_E98F28594E5AF28D (parent_contract_id), INDEX IDX_E98F2859B03A8386 (created_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE document (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, contract_id INT DEFAULT NULL, absence_id INT DEFAULT NULL, formation_id INT DEFAULT NULL, element_variable_id INT DEFAULT NULL, file_name VARCHAR(255) NOT NULL, original_name VARCHAR(255) NOT NULL, type VARCHAR(50) NOT NULL, status VARCHAR(20) DEFAULT \'pending\' NOT NULL, uploaded_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, comment LONGTEXT DEFAULT NULL, mime_type VARCHAR(100) DEFAULT NULL, file_size INT DEFAULT NULL, version INT DEFAULT 1 NOT NULL, archived TINYINT(1) DEFAULT 0 NOT NULL, archived_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', archive_reason LONGTEXT DEFAULT NULL, retention_years INT DEFAULT NULL, INDEX IDX_D8698A76A76ED395 (user_id), INDEX IDX_D8698A762576E0FD (contract_id), INDEX IDX_D8698A762DFF238F (absence_id), INDEX IDX_D8698A765200282E (formation_id), INDEX IDX_D8698A7659A52623 (element_variable_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE element_variable (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, label VARCHAR(255) NOT NULL, amount NUMERIC(10, 2) NOT NULL, period VARCHAR(7) NOT NULL, description LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_A0BCF920A76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE formation (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, title VARCHAR(255) NOT NULL, organization VARCHAR(255) DEFAULT NULL, hours NUMERIC(5, 2) DEFAULT NULL, completed_at DATE DEFAULT NULL, renewal_at DATE DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_404021BFA76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE invitation (id INT AUTO_INCREMENT NOT NULL, user_id INT DEFAULT NULL, email VARCHAR(180) NOT NULL, first_name VARCHAR(100) NOT NULL, last_name VARCHAR(100) NOT NULL, position VARCHAR(100) DEFAULT NULL, structure VARCHAR(100) DEFAULT NULL, token VARCHAR(64) NOT NULL, expires_at DATETIME NOT NULL, used_at DATETIME DEFAULT NULL, status VARCHAR(20) DEFAULT \'pending\' NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, error_message LONGTEXT DEFAULT NULL, skip_onboarding TINYINT(1) DEFAULT 0 NOT NULL, UNIQUE INDEX UNIQ_F11D61A25F37A13B (token), INDEX IDX_F11D61A2A76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE profile_update_request (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, processed_by_id INT DEFAULT NULL, requested_data JSON NOT NULL COMMENT \'(DC2Type:json)\', status VARCHAR(20) DEFAULT \'pending\' NOT NULL, reason LONGTEXT DEFAULT NULL, requested_at DATETIME NOT NULL, processed_at DATETIME DEFAULT NULL, INDEX IDX_E76C5773A76ED395 (user_id), INDEX IDX_E76C57732FFD4FD3 (processed_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE user (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL COMMENT \'(DC2Type:json)\', password VARCHAR(255) NOT NULL, first_name VARCHAR(100) NOT NULL, last_name VARCHAR(100) NOT NULL, phone VARCHAR(20) DEFAULT NULL, address LONGTEXT DEFAULT NULL, status VARCHAR(20) DEFAULT \'invited\' NOT NULL, position VARCHAR(100) DEFAULT NULL, structure VARCHAR(100) DEFAULT NULL, family_status VARCHAR(50) DEFAULT NULL, children INT DEFAULT NULL, iban VARCHAR(34) DEFAULT NULL, bic VARCHAR(11) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, cgu_accepted_at DATETIME DEFAULT NULL, submitted_at DATETIME DEFAULT NULL, matricule VARCHAR(20) DEFAULT NULL, hiring_date DATE DEFAULT NULL, UNIQUE INDEX UNIQ_8D93D64912B2DC9C (matricule), UNIQUE INDEX UNIQ_IDENTIFIER_EMAIL (email), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', available_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', delivered_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_75EA56E0FB7336F0 (queue_name), INDEX IDX_75EA56E0E3BD61CE (available_at), INDEX IDX_75EA56E016BA31DB (delivered_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE absence ADD CONSTRAINT FK_765AE0C9A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE contract ADD CONSTRAINT FK_E98F2859A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE contract ADD CONSTRAINT FK_E98F28594E5AF28D FOREIGN KEY (parent_contract_id) REFERENCES contract (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE contract ADD CONSTRAINT FK_E98F2859B03A8386 FOREIGN KEY (created_by_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE document ADD CONSTRAINT FK_D8698A76A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE document ADD CONSTRAINT FK_D8698A762576E0FD FOREIGN KEY (contract_id) REFERENCES contract (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE document ADD CONSTRAINT FK_D8698A762DFF238F FOREIGN KEY (absence_id) REFERENCES absence (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE document ADD CONSTRAINT FK_D8698A765200282E FOREIGN KEY (formation_id) REFERENCES formation (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE document ADD CONSTRAINT FK_D8698A7659A52623 FOREIGN KEY (element_variable_id) REFERENCES element_variable (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE element_variable ADD CONSTRAINT FK_A0BCF920A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE formation ADD CONSTRAINT FK_404021BFA76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE invitation ADD CONSTRAINT FK_F11D61A2A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE profile_update_request ADD CONSTRAINT FK_E76C5773A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE profile_update_request ADD CONSTRAINT FK_E76C57732FFD4FD3 FOREIGN KEY (processed_by_id) REFERENCES user (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE absence DROP FOREIGN KEY FK_765AE0C9A76ED395');
        $this->addSql('ALTER TABLE contract DROP FOREIGN KEY FK_E98F2859A76ED395');
        $this->addSql('ALTER TABLE contract DROP FOREIGN KEY FK_E98F28594E5AF28D');
        $this->addSql('ALTER TABLE contract DROP FOREIGN KEY FK_E98F2859B03A8386');
        $this->addSql('ALTER TABLE document DROP FOREIGN KEY FK_D8698A76A76ED395');
        $this->addSql('ALTER TABLE document DROP FOREIGN KEY FK_D8698A762576E0FD');
        $this->addSql('ALTER TABLE document DROP FOREIGN KEY FK_D8698A762DFF238F');
        $this->addSql('ALTER TABLE document DROP FOREIGN KEY FK_D8698A765200282E');
        $this->addSql('ALTER TABLE document DROP FOREIGN KEY FK_D8698A7659A52623');
        $this->addSql('ALTER TABLE element_variable DROP FOREIGN KEY FK_A0BCF920A76ED395');
        $this->addSql('ALTER TABLE formation DROP FOREIGN KEY FK_404021BFA76ED395');
        $this->addSql('ALTER TABLE invitation DROP FOREIGN KEY FK_F11D61A2A76ED395');
        $this->addSql('ALTER TABLE profile_update_request DROP FOREIGN KEY FK_E76C5773A76ED395');
        $this->addSql('ALTER TABLE profile_update_request DROP FOREIGN KEY FK_E76C57732FFD4FD3');
        $this->addSql('DROP TABLE absence');
        $this->addSql('DROP TABLE contract');
        $this->addSql('DROP TABLE document');
        $this->addSql('DROP TABLE element_variable');
        $this->addSql('DROP TABLE formation');
        $this->addSql('DROP TABLE invitation');
        $this->addSql('DROP TABLE profile_update_request');
        $this->addSql('DROP TABLE user');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
