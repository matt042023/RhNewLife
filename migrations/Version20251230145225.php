<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251230145225 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE planning_month DROP FOREIGN KEY FK_1F1AAA9946EA5612');
        $this->addSql('DROP INDEX IDX_1F1AAA9946EA5612 ON planning_month');
        $this->addSql('ALTER TABLE planning_month DROP squelette_id');
        $this->addSql('ALTER TABLE squelette_garde DROP FOREIGN KEY FK_AF0F96E7896DBBDE');
        $this->addSql('ALTER TABLE squelette_garde DROP FOREIGN KEY FK_AF0F96E7B03A8386');
        $this->addSql('ALTER TABLE squelette_garde ADD villa_id INT NOT NULL, ADD nombre_utilisations INT DEFAULT 0 NOT NULL, ADD derniere_utilisation DATETIME DEFAULT NULL, DROP heure_debut_jour_ecole, DROP heure_debut_weekend, DROP is_default, CHANGE created_by_id created_by_id INT DEFAULT NULL, CHANGE nom nom VARCHAR(100) NOT NULL, CHANGE configuration configuration LONGTEXT NOT NULL, CHANGE portee statut VARCHAR(20) NOT NULL');
        $this->addSql('ALTER TABLE squelette_garde ADD CONSTRAINT FK_AF0F96E7285D9761 FOREIGN KEY (villa_id) REFERENCES villa (id)');
        $this->addSql('ALTER TABLE squelette_garde ADD CONSTRAINT FK_AF0F96E7896DBBDE FOREIGN KEY (updated_by_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE squelette_garde ADD CONSTRAINT FK_AF0F96E7B03A8386 FOREIGN KEY (created_by_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_AF0F96E7285D9761 ON squelette_garde (villa_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE planning_month ADD squelette_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE planning_month ADD CONSTRAINT FK_1F1AAA9946EA5612 FOREIGN KEY (squelette_id) REFERENCES squelette_garde (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_1F1AAA9946EA5612 ON planning_month (squelette_id)');
        $this->addSql('ALTER TABLE squelette_garde DROP FOREIGN KEY FK_AF0F96E7285D9761');
        $this->addSql('ALTER TABLE squelette_garde DROP FOREIGN KEY FK_AF0F96E7B03A8386');
        $this->addSql('ALTER TABLE squelette_garde DROP FOREIGN KEY FK_AF0F96E7896DBBDE');
        $this->addSql('DROP INDEX IDX_AF0F96E7285D9761 ON squelette_garde');
        $this->addSql('ALTER TABLE squelette_garde ADD heure_debut_weekend INT NOT NULL, ADD is_default TINYINT(1) NOT NULL, DROP nombre_utilisations, DROP derniere_utilisation, CHANGE created_by_id created_by_id INT NOT NULL, CHANGE nom nom VARCHAR(255) NOT NULL, CHANGE configuration configuration JSON NOT NULL COMMENT \'(DC2Type:json)\', CHANGE villa_id heure_debut_jour_ecole INT NOT NULL, CHANGE statut portee VARCHAR(20) NOT NULL');
        $this->addSql('ALTER TABLE squelette_garde ADD CONSTRAINT FK_AF0F96E7B03A8386 FOREIGN KEY (created_by_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE squelette_garde ADD CONSTRAINT FK_AF0F96E7896DBBDE FOREIGN KEY (updated_by_id) REFERENCES user (id)');
    }
}
