<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260108134641 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Modifie les colonnes start_at et end_at de la table absence de DATETIME vers DATE (absences = jours complets uniquement)';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE absence CHANGE start_at start_at DATE NOT NULL, CHANGE end_at end_at DATE NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE absence CHANGE start_at start_at DATETIME NOT NULL, CHANGE end_at end_at DATETIME NOT NULL');
    }
}
