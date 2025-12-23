<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251223133113 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE health (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, mutuelle_enabled TINYINT(1) DEFAULT 0 NOT NULL, mutuelle_nom VARCHAR(255) DEFAULT NULL, mutuelle_formule VARCHAR(255) DEFAULT NULL, mutuelle_date_fin DATE DEFAULT NULL, prevoyance_enabled TINYINT(1) DEFAULT 0 NOT NULL, prevoyance_nom VARCHAR(255) DEFAULT NULL, UNIQUE INDEX UNIQ_CEDA2313A76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE health ADD CONSTRAINT FK_CEDA2313A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE health DROP FOREIGN KEY FK_CEDA2313A76ED395');
        $this->addSql('DROP TABLE health');
    }
}
