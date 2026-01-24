<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260123201645 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE annonce_interne (id INT AUTO_INCREMENT NOT NULL, publie_par_id INT NOT NULL, titre VARCHAR(255) NOT NULL, contenu LONGTEXT NOT NULL, date_publication DATETIME NOT NULL, visibilite VARCHAR(20) DEFAULT \'tous\' NOT NULL, image VARCHAR(500) DEFAULT NULL, epingle TINYINT(1) DEFAULT 0 NOT NULL, date_expiration DATETIME DEFAULT NULL, actif TINYINT(1) DEFAULT 1 NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_DAAA0CFC801A2092 (publie_par_id), INDEX idx_annonce_date (date_publication), INDEX idx_annonce_epingle (epingle), INDEX idx_annonce_actif (actif), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE message_interne (id INT AUTO_INCREMENT NOT NULL, expediteur_id INT NOT NULL, roles_cible JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', sujet VARCHAR(255) NOT NULL, contenu LONGTEXT NOT NULL, date_envoi DATETIME NOT NULL, pieces_jointes JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', lu_par JSON NOT NULL COMMENT \'(DC2Type:json)\', created_at DATETIME NOT NULL, INDEX idx_message_expediteur (expediteur_id), INDEX idx_message_date (date_envoi), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE message_interne_destinataires (message_interne_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_378918B8918DB3B8 (message_interne_id), INDEX IDX_378918B8A76ED395 (user_id), PRIMARY KEY(message_interne_id, user_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE notification (id INT AUTO_INCREMENT NOT NULL, cible_user_id INT DEFAULT NULL, type VARCHAR(20) NOT NULL, titre VARCHAR(255) NOT NULL, message LONGTEXT NOT NULL, lien VARCHAR(500) DEFAULT NULL, roles_cible JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', date_envoi DATETIME NOT NULL, lu TINYINT(1) DEFAULT 0 NOT NULL, lu_at DATETIME DEFAULT NULL, source_event VARCHAR(50) DEFAULT NULL, source_entity_id INT DEFAULT NULL, created_at DATETIME NOT NULL, INDEX idx_notification_user (cible_user_id), INDEX idx_notification_date (date_envoi), INDEX idx_notification_lu (lu), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE annonce_interne ADD CONSTRAINT FK_DAAA0CFC801A2092 FOREIGN KEY (publie_par_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE message_interne ADD CONSTRAINT FK_B04DAC9010335F61 FOREIGN KEY (expediteur_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE message_interne_destinataires ADD CONSTRAINT FK_378918B8918DB3B8 FOREIGN KEY (message_interne_id) REFERENCES message_interne (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE message_interne_destinataires ADD CONSTRAINT FK_378918B8A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE notification ADD CONSTRAINT FK_BF5476CA6A2544E6 FOREIGN KEY (cible_user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE message DROP FOREIGN KEY FK_B6BD307FF624B39D');
        $this->addSql('ALTER TABLE message_recipient DROP FOREIGN KEY FK_2BDFD7F537A1329');
        $this->addSql('ALTER TABLE message_recipient DROP FOREIGN KEY FK_2BDFD7FE92F8F78');
        $this->addSql('DROP TABLE message');
        $this->addSql('DROP TABLE message_recipient');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE message (id INT AUTO_INCREMENT NOT NULL, sender_id INT DEFAULT NULL, subject VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, body LONGTEXT CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, priority VARCHAR(20) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, status VARCHAR(20) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_B6BD307FF624B39D (sender_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE message_recipient (id INT AUTO_INCREMENT NOT NULL, message_id INT NOT NULL, recipient_id INT NOT NULL, is_read TINYINT(1) DEFAULT 0 NOT NULL, read_at DATETIME DEFAULT NULL, deleted_by_recipient TINYINT(1) DEFAULT 0 NOT NULL, INDEX idx_recipient_read (recipient_id, is_read), INDEX IDX_2BDFD7F537A1329 (message_id), INDEX idx_recipient_deleted (recipient_id, deleted_by_recipient), INDEX IDX_2BDFD7FE92F8F78 (recipient_id), UNIQUE INDEX uniq_message_recipient (message_id, recipient_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE message ADD CONSTRAINT FK_B6BD307FF624B39D FOREIGN KEY (sender_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE message_recipient ADD CONSTRAINT FK_2BDFD7F537A1329 FOREIGN KEY (message_id) REFERENCES message (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE message_recipient ADD CONSTRAINT FK_2BDFD7FE92F8F78 FOREIGN KEY (recipient_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE annonce_interne DROP FOREIGN KEY FK_DAAA0CFC801A2092');
        $this->addSql('ALTER TABLE message_interne DROP FOREIGN KEY FK_B04DAC9010335F61');
        $this->addSql('ALTER TABLE message_interne_destinataires DROP FOREIGN KEY FK_378918B8918DB3B8');
        $this->addSql('ALTER TABLE message_interne_destinataires DROP FOREIGN KEY FK_378918B8A76ED395');
        $this->addSql('ALTER TABLE notification DROP FOREIGN KEY FK_BF5476CA6A2544E6');
        $this->addSql('DROP TABLE annonce_interne');
        $this->addSql('DROP TABLE message_interne');
        $this->addSql('DROP TABLE message_interne_destinataires');
        $this->addSql('DROP TABLE notification');
    }
}
