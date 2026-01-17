<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260116192510 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE jour_chome (id INT AUTO_INCREMENT NOT NULL, educateur_id INT NOT NULL, created_by_id INT DEFAULT NULL, date DATE NOT NULL, notes LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, INDEX IDX_4A0A883F6BFC1A0E (educateur_id), INDEX IDX_4A0A883FB03A8386 (created_by_id), UNIQUE INDEX unique_educateur_date (educateur_id, date), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE jour_chome ADD CONSTRAINT FK_4A0A883F6BFC1A0E FOREIGN KEY (educateur_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE jour_chome ADD CONSTRAINT FK_4A0A883FB03A8386 FOREIGN KEY (created_by_id) REFERENCES user (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE jour_chome DROP FOREIGN KEY FK_4A0A883F6BFC1A0E');
        $this->addSql('ALTER TABLE jour_chome DROP FOREIGN KEY FK_4A0A883FB03A8386');
        $this->addSql('DROP TABLE jour_chome');
    }
}
