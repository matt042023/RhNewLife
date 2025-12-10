<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration pour le module Rendez-vous (Appointments)
 * - Ajoute les nouveaux champs à la table rendez_vous
 * - Crée la table appointment_participants
 * - Migre les données existantes
 */
final class Version20251209144500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute les champs du module Rendez-vous et crée la table appointment_participants';
    }

    public function up(Schema $schema): void
    {
        // Ajouter les nouveaux champs à rendez_vous
        $this->addSql('ALTER TABLE rendez_vous ADD organizer_id INT NOT NULL AFTER created_by_id');
        $this->addSql('ALTER TABLE rendez_vous ADD duration_minutes INT DEFAULT 60');
        $this->addSql('ALTER TABLE rendez_vous ADD subject VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE rendez_vous ADD location VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE rendez_vous ADD creates_absence TINYINT(1) DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE rendez_vous ADD refusal_reason LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE rendez_vous ADD created_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE rendez_vous ADD updated_at DATETIME NOT NULL');

        // Ajouter l'index et la foreign key pour organizer
        $this->addSql('ALTER TABLE rendez_vous ADD CONSTRAINT FK_65E8AA0A876C4DDA FOREIGN KEY (organizer_id) REFERENCES user (id)');
        $this->addSql('CREATE INDEX IDX_65E8AA0A876C4DDA ON rendez_vous (organizer_id)');

        // Créer la table appointment_participants
        $this->addSql('CREATE TABLE appointment_participants (
            id INT AUTO_INCREMENT NOT NULL,
            appointment_id INT NOT NULL,
            user_id INT NOT NULL,
            presence_status VARCHAR(20) NOT NULL DEFAULT \'PENDING\',
            confirmed_at DATETIME DEFAULT NULL,
            linked_absence_id INT DEFAULT NULL,
            created_at DATETIME NOT NULL,
            INDEX IDX_B56E3E0BE5B533F9 (appointment_id),
            INDEX IDX_B56E3E0BA76ED395 (user_id),
            INDEX IDX_B56E3E0B8E962C16 (linked_absence_id),
            UNIQUE INDEX unique_appointment_user (appointment_id, user_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Ajouter les foreign keys pour appointment_participants
        $this->addSql('ALTER TABLE appointment_participants ADD CONSTRAINT FK_B56E3E0BE5B533F9 FOREIGN KEY (appointment_id) REFERENCES rendez_vous (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE appointment_participants ADD CONSTRAINT FK_B56E3E0BA76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE appointment_participants ADD CONSTRAINT FK_B56E3E0B8E962C16 FOREIGN KEY (linked_absence_id) REFERENCES absence (id) ON DELETE SET NULL');

        // Migrer les données existantes
        // 1. Copier created_by vers organizer_id
        $this->addSql('UPDATE rendez_vous SET organizer_id = created_by_id');

        // 2. Copier titre vers subject
        $this->addSql('UPDATE rendez_vous SET subject = titre');

        // 3. Migrer les statuts anciens vers nouveaux
        $this->addSql('UPDATE rendez_vous SET statut = \'CONFIRME\' WHERE statut = \'planned\'');
        $this->addSql('UPDATE rendez_vous SET statut = \'ANNULE\' WHERE statut = \'cancelled\'');
        $this->addSql('UPDATE rendez_vous SET statut = \'TERMINE\' WHERE statut = \'completed\'');

        // 4. Migrer les types anciens vers nouveaux (tous deviennent CONVOCATION par défaut)
        $this->addSql('UPDATE rendez_vous SET type = \'CONVOCATION\' WHERE type IN (\'individuel\', \'groupe\')');

        // 5. Calculer duration_minutes à partir de start_at et end_at
        $this->addSql('UPDATE rendez_vous SET duration_minutes = TIMESTAMPDIFF(MINUTE, start_at, end_at) WHERE end_at IS NOT NULL AND start_at IS NOT NULL');

        // 6. Initialiser les timestamps
        $this->addSql('UPDATE rendez_vous SET created_at = NOW(), updated_at = NOW() WHERE created_at IS NULL');

        // 7. Migrer les participants de rendez_vous_user vers appointment_participants
        $this->addSql('INSERT INTO appointment_participants (appointment_id, user_id, presence_status, created_at)
            SELECT rendez_vous_id, user_id, \'PENDING\', NOW()
            FROM rendez_vous_user');
    }

    public function down(Schema $schema): void
    {
        // Supprimer la table appointment_participants
        $this->addSql('ALTER TABLE appointment_participants DROP FOREIGN KEY FK_B56E3E0BE5B533F9');
        $this->addSql('ALTER TABLE appointment_participants DROP FOREIGN KEY FK_B56E3E0BA76ED395');
        $this->addSql('ALTER TABLE appointment_participants DROP FOREIGN KEY FK_B56E3E0B8E962C16');
        $this->addSql('DROP TABLE appointment_participants');

        // Supprimer les nouveaux champs de rendez_vous
        $this->addSql('ALTER TABLE rendez_vous DROP FOREIGN KEY FK_65E8AA0A876C4DDA');
        $this->addSql('DROP INDEX IDX_65E8AA0A876C4DDA ON rendez_vous');
        $this->addSql('ALTER TABLE rendez_vous DROP organizer_id');
        $this->addSql('ALTER TABLE rendez_vous DROP duration_minutes');
        $this->addSql('ALTER TABLE rendez_vous DROP subject');
        $this->addSql('ALTER TABLE rendez_vous DROP location');
        $this->addSql('ALTER TABLE rendez_vous DROP creates_absence');
        $this->addSql('ALTER TABLE rendez_vous DROP refusal_reason');
        $this->addSql('ALTER TABLE rendez_vous DROP created_at');
        $this->addSql('ALTER TABLE rendez_vous DROP updated_at');

        // Restaurer les anciens statuts
        $this->addSql('UPDATE rendez_vous SET statut = \'planned\' WHERE statut = \'CONFIRME\'');
        $this->addSql('UPDATE rendez_vous SET statut = \'cancelled\' WHERE statut = \'ANNULE\'');
        $this->addSql('UPDATE rendez_vous SET statut = \'completed\' WHERE statut = \'TERMINE\'');

        // Restaurer les anciens types
        $this->addSql('UPDATE rendez_vous SET type = \'individuel\' WHERE type = \'CONVOCATION\'');
    }
}
