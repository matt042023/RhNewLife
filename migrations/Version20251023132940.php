<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251023132940 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'EP-02: Add Contract entity, ProfileUpdateRequest entity, extend User (matricule, hiringDate), extend Invitation (skipOnboarding), extend Document (contract relation)';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE profile_update_request (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, processed_by_id INT DEFAULT NULL, requested_data JSON NOT NULL COMMENT \'(DC2Type:json)\', status VARCHAR(20) DEFAULT \'pending\' NOT NULL, reason LONGTEXT DEFAULT NULL, requested_at DATETIME NOT NULL, processed_at DATETIME DEFAULT NULL, INDEX IDX_E76C5773A76ED395 (user_id), INDEX IDX_E76C57732FFD4FD3 (processed_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE profile_update_request ADD CONSTRAINT FK_E76C5773A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE profile_update_request ADD CONSTRAINT FK_E76C57732FFD4FD3 FOREIGN KEY (processed_by_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE contract DROP INDEX UNIQ_E98F2859A76ED395, ADD INDEX IDX_E98F2859A76ED395 (user_id)');
        $this->addSql('DROP INDEX IDX_CONTRACT_END_DATE ON contract');
        $this->addSql('DROP INDEX IDX_CONTRACT_START_DATE ON contract');
        $this->addSql('DROP INDEX IDX_CONTRACT_STATUS ON contract');
        $this->addSql('DROP INDEX IDX_CONTRACT_TYPE ON contract');
        $this->addSql('ALTER TABLE contract ADD parent_contract_id INT DEFAULT NULL, ADD created_by_id INT DEFAULT NULL, ADD working_days JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', ADD villa VARCHAR(100) DEFAULT NULL, ADD activity_rate NUMERIC(3, 2) DEFAULT \'1.00\' NOT NULL, ADD termination_reason LONGTEXT DEFAULT NULL, ADD signed_at DATETIME DEFAULT NULL, ADD terminated_at DATETIME DEFAULT NULL, DROP jours_travail, DROP closure_reason, CHANGE type type VARCHAR(50) NOT NULL, CHANGE status status VARCHAR(20) DEFAULT \'draft\' NOT NULL, CHANGE essai_fin essai_end_date DATE DEFAULT NULL, CHANGE salaire base_salary NUMERIC(10, 2) NOT NULL, CHANGE closed_at validated_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE contract ADD CONSTRAINT FK_E98F28594E5AF28D FOREIGN KEY (parent_contract_id) REFERENCES contract (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE contract ADD CONSTRAINT FK_E98F2859B03A8386 FOREIGN KEY (created_by_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_E98F28594E5AF28D ON contract (parent_contract_id)');
        $this->addSql('CREATE INDEX IDX_E98F2859B03A8386 ON contract (created_by_id)');
        $this->addSql('ALTER TABLE document ADD contract_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE document ADD CONSTRAINT FK_D8698A762576E0FD FOREIGN KEY (contract_id) REFERENCES contract (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_D8698A762576E0FD ON document (contract_id)');
        $this->addSql('ALTER TABLE invitation ADD skip_onboarding TINYINT(1) DEFAULT 0 NOT NULL');
        $this->addSql('DROP INDEX IDX_USER_STATUS ON user');
        $this->addSql('ALTER TABLE user CHANGE birth_date hiring_date DATE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE profile_update_request DROP FOREIGN KEY FK_E76C5773A76ED395');
        $this->addSql('ALTER TABLE profile_update_request DROP FOREIGN KEY FK_E76C57732FFD4FD3');
        $this->addSql('DROP TABLE profile_update_request');
        $this->addSql('ALTER TABLE contract DROP INDEX IDX_E98F2859A76ED395, ADD UNIQUE INDEX UNIQ_E98F2859A76ED395 (user_id)');
        $this->addSql('ALTER TABLE contract DROP FOREIGN KEY FK_E98F28594E5AF28D');
        $this->addSql('ALTER TABLE contract DROP FOREIGN KEY FK_E98F2859B03A8386');
        $this->addSql('DROP INDEX IDX_E98F28594E5AF28D ON contract');
        $this->addSql('DROP INDEX IDX_E98F2859B03A8386 ON contract');
        $this->addSql('ALTER TABLE contract ADD jours_travail JSON NOT NULL COMMENT \'(DC2Type:json)\', ADD closed_at DATETIME DEFAULT NULL, ADD closure_reason VARCHAR(255) DEFAULT NULL, DROP parent_contract_id, DROP created_by_id, DROP working_days, DROP villa, DROP activity_rate, DROP termination_reason, DROP validated_at, DROP signed_at, DROP terminated_at, CHANGE type type VARCHAR(20) NOT NULL, CHANGE status status VARCHAR(20) DEFAULT \'actif\' NOT NULL, CHANGE base_salary salaire NUMERIC(10, 2) NOT NULL, CHANGE essai_end_date essai_fin DATE DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_CONTRACT_END_DATE ON contract (end_date)');
        $this->addSql('CREATE INDEX IDX_CONTRACT_START_DATE ON contract (start_date)');
        $this->addSql('CREATE INDEX IDX_CONTRACT_STATUS ON contract (status)');
        $this->addSql('CREATE INDEX IDX_CONTRACT_TYPE ON contract (type)');
        $this->addSql('ALTER TABLE document DROP FOREIGN KEY FK_D8698A762576E0FD');
        $this->addSql('DROP INDEX IDX_D8698A762576E0FD ON document');
        $this->addSql('ALTER TABLE document DROP contract_id');
        $this->addSql('ALTER TABLE invitation DROP skip_onboarding');
        $this->addSql('ALTER TABLE user CHANGE hiring_date birth_date DATE DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_USER_STATUS ON user (status)');
    }
}
