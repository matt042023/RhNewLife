<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251220173329 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajout du système de compteur annuel de jours pour les éducateurs';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE compteur_jours_annuels (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, year INT NOT NULL, jours_alloues NUMERIC(5, 2) NOT NULL, jours_consommes NUMERIC(5, 2) DEFAULT \'0.00\' NOT NULL, date_embauche DATE DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_5FD9D8C0A76ED395 (user_id), UNIQUE INDEX unique_user_year (user_id, year), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE compteur_jours_annuels ADD CONSTRAINT FK_5FD9D8C0A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE contract ADD use_annual_day_system TINYINT(1) DEFAULT 0 NOT NULL, ADD annual_days_required NUMERIC(5, 2) DEFAULT NULL, ADD annual_day_notes LONGTEXT DEFAULT NULL, CHANGE activity_rate activity_rate NUMERIC(3, 2) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE compteur_jours_annuels DROP FOREIGN KEY FK_5FD9D8C0A76ED395');
        $this->addSql('DROP TABLE compteur_jours_annuels');
        $this->addSql('ALTER TABLE contract DROP use_annual_day_system, DROP annual_days_required, DROP annual_day_notes, CHANGE activity_rate activity_rate NUMERIC(3, 2) DEFAULT \'1.00\' NOT NULL');
    }
}
