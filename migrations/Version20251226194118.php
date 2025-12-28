<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251226194118 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE visite_medicale ADD appointment_id INT DEFAULT NULL, ADD status VARCHAR(50) NOT NULL, CHANGE visit_date visit_date DATE DEFAULT NULL, CHANGE expiry_date expiry_date DATE DEFAULT NULL, CHANGE medical_organization medical_organization VARCHAR(255) DEFAULT NULL, CHANGE aptitude aptitude VARCHAR(50) DEFAULT NULL');
        $this->addSql('ALTER TABLE visite_medicale ADD CONSTRAINT FK_B6D49D3FE5B533F9 FOREIGN KEY (appointment_id) REFERENCES rendez_vous (id) ON DELETE SET NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_B6D49D3FE5B533F9 ON visite_medicale (appointment_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE visite_medicale DROP FOREIGN KEY FK_B6D49D3FE5B533F9');
        $this->addSql('DROP INDEX UNIQ_B6D49D3FE5B533F9 ON visite_medicale');
        $this->addSql('ALTER TABLE visite_medicale DROP appointment_id, DROP status, CHANGE visit_date visit_date DATE NOT NULL, CHANGE expiry_date expiry_date DATE NOT NULL, CHANGE medical_organization medical_organization VARCHAR(255) NOT NULL, CHANGE aptitude aptitude VARCHAR(50) NOT NULL');
    }
}
