<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260106144925 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE appointment_participants DROP FOREIGN KEY FK_88AB679FBA3BF8C8');
        $this->addSql('DROP INDEX IDX_88AB679FBA3BF8C8 ON appointment_participants');
        $this->addSql('ALTER TABLE appointment_participants DROP linked_absence_id');
        $this->addSql('ALTER TABLE rendez_vous DROP creates_absence');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE appointment_participants ADD linked_absence_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE appointment_participants ADD CONSTRAINT FK_88AB679FBA3BF8C8 FOREIGN KEY (linked_absence_id) REFERENCES absence (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_88AB679FBA3BF8C8 ON appointment_participants (linked_absence_id)');
        $this->addSql('ALTER TABLE rendez_vous ADD creates_absence TINYINT(1) DEFAULT 0 NOT NULL');
    }
}
