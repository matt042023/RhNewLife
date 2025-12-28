<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251228145801 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create astreinte table for on-call duty management';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE astreinte (id INT AUTO_INCREMENT NOT NULL, educateur_id INT DEFAULT NULL, created_by_id INT DEFAULT NULL, updated_by_id INT DEFAULT NULL, start_at DATETIME NOT NULL, end_at DATETIME NOT NULL, period_label VARCHAR(50) DEFAULT NULL, status VARCHAR(30) NOT NULL, replacement_count INT DEFAULT 0 NOT NULL, notes LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_F23DC0736BFC1A0E (educateur_id), INDEX IDX_F23DC073B03A8386 (created_by_id), INDEX IDX_F23DC073896DBBDE (updated_by_id), INDEX IDX_period (start_at, end_at), INDEX IDX_status (status), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE astreinte ADD CONSTRAINT FK_F23DC0736BFC1A0E FOREIGN KEY (educateur_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE astreinte ADD CONSTRAINT FK_F23DC073B03A8386 FOREIGN KEY (created_by_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE astreinte ADD CONSTRAINT FK_F23DC073896DBBDE FOREIGN KEY (updated_by_id) REFERENCES user (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE astreinte DROP FOREIGN KEY FK_F23DC0736BFC1A0E');
        $this->addSql('ALTER TABLE astreinte DROP FOREIGN KEY FK_F23DC073B03A8386');
        $this->addSql('ALTER TABLE astreinte DROP FOREIGN KEY FK_F23DC073896DBBDE');
        $this->addSql('DROP TABLE astreinte');
    }
}
