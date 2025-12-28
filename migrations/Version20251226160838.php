<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251226160838 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE visite_medicale (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, type VARCHAR(50) NOT NULL, visit_date DATE NOT NULL, expiry_date DATE NOT NULL, medical_organization VARCHAR(255) NOT NULL, aptitude VARCHAR(50) NOT NULL, observations LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_B6D49D3FA76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE visite_medicale ADD CONSTRAINT FK_B6D49D3FA76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE document ADD visite_medicale_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE document ADD CONSTRAINT FK_D8698A76C2785B1 FOREIGN KEY (visite_medicale_id) REFERENCES visite_medicale (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_D8698A76C2785B1 ON document (visite_medicale_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE document DROP FOREIGN KEY FK_D8698A76C2785B1');
        $this->addSql('ALTER TABLE visite_medicale DROP FOREIGN KEY FK_B6D49D3FA76ED395');
        $this->addSql('DROP TABLE visite_medicale');
        $this->addSql('DROP INDEX IDX_D8698A76C2785B1 ON document');
        $this->addSql('ALTER TABLE document DROP visite_medicale_id');
    }
}
