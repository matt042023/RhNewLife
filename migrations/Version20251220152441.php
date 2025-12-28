<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251220152441 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE appointment_participants CHANGE presence_status presence_status VARCHAR(20) NOT NULL');
        $this->addSql('ALTER TABLE appointment_participants RENAME INDEX idx_b56e3e0be5b533f9 TO IDX_88AB679FE5B533F9');
        $this->addSql('ALTER TABLE appointment_participants RENAME INDEX idx_b56e3e0ba76ed395 TO IDX_88AB679FA76ED395');
        $this->addSql('ALTER TABLE appointment_participants RENAME INDEX idx_b56e3e0b8e962c16 TO IDX_88AB679FBA3BF8C8');
        $this->addSql('ALTER TABLE contract ADD villa_id INT DEFAULT NULL, DROP villa');
        $this->addSql('ALTER TABLE contract ADD CONSTRAINT FK_E98F2859285D9761 FOREIGN KEY (villa_id) REFERENCES villa (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_E98F2859285D9761 ON contract (villa_id)');
        $this->addSql('ALTER TABLE invitation ADD villa_id INT DEFAULT NULL, DROP structure');
        $this->addSql('ALTER TABLE invitation ADD CONSTRAINT FK_F11D61A2285D9761 FOREIGN KEY (villa_id) REFERENCES villa (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_F11D61A2285D9761 ON invitation (villa_id)');
        $this->addSql('ALTER TABLE rendez_vous CHANGE duration_minutes duration_minutes INT DEFAULT NULL');
        $this->addSql('ALTER TABLE user ADD villa_id INT DEFAULT NULL, DROP structure');
        $this->addSql('ALTER TABLE user ADD CONSTRAINT FK_8D93D649285D9761 FOREIGN KEY (villa_id) REFERENCES villa (id) ON DELETE RESTRICT');
        $this->addSql('CREATE INDEX IDX_8D93D649285D9761 ON user (villa_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE appointment_participants CHANGE presence_status presence_status VARCHAR(20) DEFAULT \'PENDING\' NOT NULL');
        $this->addSql('ALTER TABLE appointment_participants RENAME INDEX idx_88ab679fe5b533f9 TO IDX_B56E3E0BE5B533F9');
        $this->addSql('ALTER TABLE appointment_participants RENAME INDEX idx_88ab679fa76ed395 TO IDX_B56E3E0BA76ED395');
        $this->addSql('ALTER TABLE appointment_participants RENAME INDEX idx_88ab679fba3bf8c8 TO IDX_B56E3E0B8E962C16');
        $this->addSql('ALTER TABLE contract DROP FOREIGN KEY FK_E98F2859285D9761');
        $this->addSql('DROP INDEX IDX_E98F2859285D9761 ON contract');
        $this->addSql('ALTER TABLE contract ADD villa VARCHAR(100) DEFAULT NULL, DROP villa_id');
        $this->addSql('ALTER TABLE invitation DROP FOREIGN KEY FK_F11D61A2285D9761');
        $this->addSql('DROP INDEX IDX_F11D61A2285D9761 ON invitation');
        $this->addSql('ALTER TABLE invitation ADD structure VARCHAR(100) DEFAULT NULL, DROP villa_id');
        $this->addSql('ALTER TABLE rendez_vous CHANGE duration_minutes duration_minutes INT DEFAULT 60');
        $this->addSql('ALTER TABLE user DROP FOREIGN KEY FK_8D93D649285D9761');
        $this->addSql('DROP INDEX IDX_8D93D649285D9761 ON user');
        $this->addSql('ALTER TABLE user ADD structure VARCHAR(100) DEFAULT NULL, DROP villa_id');
    }
}
