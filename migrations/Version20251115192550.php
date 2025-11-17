<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251115192550 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Augmente la longueur de la colonne status de 20 à 30 caractères pour supporter SIGNED_PENDING_VALIDATION';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE contract CHANGE status status VARCHAR(30) DEFAULT \'DRAFT\' NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE contract CHANGE status status VARCHAR(20) DEFAULT \'DRAFT\' NOT NULL');
    }
}
