<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251230152948 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE squelette_garde DROP FOREIGN KEY FK_AF0F96E7285D9761');
        $this->addSql('DROP INDEX IDX_AF0F96E7285D9761 ON squelette_garde');
        $this->addSql('ALTER TABLE squelette_garde DROP villa_id');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE squelette_garde ADD villa_id INT NOT NULL');
        $this->addSql('ALTER TABLE squelette_garde ADD CONSTRAINT FK_AF0F96E7285D9761 FOREIGN KEY (villa_id) REFERENCES villa (id)');
        $this->addSql('CREATE INDEX IDX_AF0F96E7285D9761 ON squelette_garde (villa_id)');
    }
}
