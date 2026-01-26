<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260124132300 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE absence (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, absence_type_id INT DEFAULT NULL, validated_by_id INT DEFAULT NULL, type VARCHAR(50) DEFAULT NULL, start_at DATE NOT NULL, end_at DATE NOT NULL, reason LONGTEXT DEFAULT NULL, status VARCHAR(30) NOT NULL, justification_status VARCHAR(30) DEFAULT NULL, justification_deadline DATETIME DEFAULT NULL, rejection_reason LONGTEXT DEFAULT NULL, admin_comment LONGTEXT DEFAULT NULL, affects_planning TINYINT(1) DEFAULT 0 NOT NULL, working_days_count DOUBLE PRECISION DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_765AE0C9A76ED395 (user_id), INDEX IDX_765AE0C9CCAA91B (absence_type_id), INDEX IDX_765AE0C9C69DE5E5 (validated_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE affectation (id INT AUTO_INCREMENT NOT NULL, planning_mois_id INT NOT NULL, user_id INT DEFAULT NULL, villa_id INT DEFAULT NULL, start_at DATETIME NOT NULL, end_at DATETIME NOT NULL, type VARCHAR(20) NOT NULL, statut VARCHAR(30) NOT NULL, is_from_squelette TINYINT(1) NOT NULL, commentaire LONGTEXT DEFAULT NULL, jours_travailes INT DEFAULT NULL, is_segmented TINYINT(1) DEFAULT 0 NOT NULL, segment_number INT DEFAULT NULL, total_segments INT DEFAULT NULL, INDEX IDX_F4DD61D3276A1453 (planning_mois_id), INDEX IDX_F4DD61D3A76ED395 (user_id), INDEX IDX_F4DD61D3285D9761 (villa_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE annonce_interne (id INT AUTO_INCREMENT NOT NULL, publie_par_id INT NOT NULL, titre VARCHAR(255) NOT NULL, contenu LONGTEXT NOT NULL, date_publication DATETIME NOT NULL, visibilite VARCHAR(20) DEFAULT \'tous\' NOT NULL, image VARCHAR(500) DEFAULT NULL, epingle TINYINT(1) DEFAULT 0 NOT NULL, date_expiration DATETIME DEFAULT NULL, actif TINYINT(1) DEFAULT 1 NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_DAAA0CFC801A2092 (publie_par_id), INDEX idx_annonce_date (date_publication), INDEX idx_annonce_epingle (epingle), INDEX idx_annonce_actif (actif), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE appointment_participants (id INT AUTO_INCREMENT NOT NULL, appointment_id INT NOT NULL, user_id INT NOT NULL, presence_status VARCHAR(20) NOT NULL, confirmed_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, INDEX IDX_88AB679FE5B533F9 (appointment_id), INDEX IDX_88AB679FA76ED395 (user_id), UNIQUE INDEX unique_appointment_user (appointment_id, user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE astreinte (id INT AUTO_INCREMENT NOT NULL, educateur_id INT DEFAULT NULL, created_by_id INT DEFAULT NULL, updated_by_id INT DEFAULT NULL, start_at DATETIME NOT NULL, end_at DATETIME NOT NULL, period_label VARCHAR(50) DEFAULT NULL, status VARCHAR(30) NOT NULL, replacement_count INT DEFAULT 0 NOT NULL, notes LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_F23DC0736BFC1A0E (educateur_id), INDEX IDX_F23DC073B03A8386 (created_by_id), INDEX IDX_F23DC073896DBBDE (updated_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE compteur_absence (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, absence_type_id INT NOT NULL, year INT NOT NULL, earned DOUBLE PRECISION DEFAULT \'0\' NOT NULL, taken DOUBLE PRECISION DEFAULT \'0\' NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_C49D8C0BA76ED395 (user_id), INDEX IDX_C49D8C0BCCAA91B (absence_type_id), UNIQUE INDEX unique_user_type_year (user_id, absence_type_id, year), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE compteur_cp (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, periode_reference VARCHAR(9) NOT NULL, solde_initial DOUBLE PRECISION NOT NULL, acquis DOUBLE PRECISION NOT NULL, pris DOUBLE PRECISION NOT NULL, ajustement_admin DOUBLE PRECISION NOT NULL, ajustement_comment LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_4A0EA4E7A76ED395 (user_id), UNIQUE INDEX unique_user_periode (user_id, periode_reference), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE compteur_jours_annuels (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, year INT NOT NULL, jours_alloues DOUBLE PRECISION NOT NULL, jours_consommes DOUBLE PRECISION NOT NULL, ajustement_admin DOUBLE PRECISION NOT NULL, ajustement_comment LONGTEXT DEFAULT NULL, date_embauche DATE DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_5FD9D8C0A76ED395 (user_id), UNIQUE INDEX unique_user_year (user_id, year), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE consolidation_paie (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, validated_by_id INT DEFAULT NULL, period VARCHAR(7) NOT NULL, status VARCHAR(20) DEFAULT \'draft\' NOT NULL, jours_travailes DOUBLE PRECISION NOT NULL, jours_evenements DOUBLE PRECISION NOT NULL, jours_absence JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', cp_acquis DOUBLE PRECISION NOT NULL, cp_pris DOUBLE PRECISION NOT NULL, cp_solde_debut DOUBLE PRECISION NOT NULL, cp_solde_fin DOUBLE PRECISION NOT NULL, total_variables DOUBLE PRECISION NOT NULL, validated_at DATETIME DEFAULT NULL, exported_at DATETIME DEFAULT NULL, sent_to_accountant_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_C865968DA76ED395 (user_id), INDEX IDX_C865968DC69DE5E5 (validated_by_id), UNIQUE INDEX unique_user_period (user_id, period), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE consolidation_paie_history (id INT AUTO_INCREMENT NOT NULL, consolidation_id INT NOT NULL, modified_by_id INT NOT NULL, action VARCHAR(50) NOT NULL, field VARCHAR(100) DEFAULT NULL, old_value LONGTEXT DEFAULT NULL, new_value LONGTEXT DEFAULT NULL, modified_at DATETIME NOT NULL, comment LONGTEXT DEFAULT NULL, INDEX IDX_96887FC8F48F4028 (consolidation_id), INDEX IDX_96887FC899049ECE (modified_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE contract (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, villa_id INT DEFAULT NULL, parent_contract_id INT DEFAULT NULL, created_by_id INT DEFAULT NULL, template_id INT DEFAULT NULL, validated_by_id INT DEFAULT NULL, type VARCHAR(50) NOT NULL, start_date DATE NOT NULL, end_date DATE DEFAULT NULL, essai_end_date DATE DEFAULT NULL, base_salary NUMERIC(10, 2) NOT NULL, prime NUMERIC(10, 2) DEFAULT NULL, working_days JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', activity_rate NUMERIC(3, 2) DEFAULT NULL, weekly_hours NUMERIC(5, 2) DEFAULT NULL, use_annual_day_system TINYINT(1) DEFAULT 0 NOT NULL, annual_days_required NUMERIC(5, 2) DEFAULT NULL, annual_day_notes LONGTEXT DEFAULT NULL, mutuelle TINYINT(1) DEFAULT 0 NOT NULL, prevoyance TINYINT(1) DEFAULT 0 NOT NULL, status VARCHAR(30) DEFAULT \'DRAFT\' NOT NULL, version INT DEFAULT 1 NOT NULL, termination_reason LONGTEXT DEFAULT NULL, notes LONGTEXT DEFAULT NULL, draft_file_url VARCHAR(500) DEFAULT NULL, signed_file_url VARCHAR(500) DEFAULT NULL, signature_token VARCHAR(100) DEFAULT NULL, token_expires_at DATETIME DEFAULT NULL, signature_ip VARCHAR(45) DEFAULT NULL, signature_user_agent LONGTEXT DEFAULT NULL, validated_at DATETIME DEFAULT NULL, signed_at DATETIME DEFAULT NULL, terminated_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_E98F2859D7605360 (signature_token), INDEX IDX_E98F2859A76ED395 (user_id), INDEX IDX_E98F2859285D9761 (villa_id), INDEX IDX_E98F28594E5AF28D (parent_contract_id), INDEX IDX_E98F2859B03A8386 (created_by_id), INDEX IDX_E98F28595DA0FB8 (template_id), INDEX IDX_E98F2859C69DE5E5 (validated_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE document (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, contract_id INT DEFAULT NULL, absence_id INT DEFAULT NULL, formation_id INT DEFAULT NULL, element_variable_id INT DEFAULT NULL, visite_medicale_id INT DEFAULT NULL, validated_by_id INT DEFAULT NULL, uploaded_by_id INT DEFAULT NULL, file_name VARCHAR(255) NOT NULL, original_name VARCHAR(255) NOT NULL, type VARCHAR(50) NOT NULL, status VARCHAR(20) DEFAULT \'pending\' NOT NULL, uploaded_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, comment LONGTEXT DEFAULT NULL, mime_type VARCHAR(100) DEFAULT NULL, file_size INT DEFAULT NULL, version INT DEFAULT 1 NOT NULL, archived_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', archive_reason LONGTEXT DEFAULT NULL, retention_years INT DEFAULT NULL, validated_at DATETIME DEFAULT NULL, rejected_at DATETIME DEFAULT NULL, rejection_reason LONGTEXT DEFAULT NULL, INDEX IDX_D8698A76A76ED395 (user_id), INDEX IDX_D8698A762576E0FD (contract_id), INDEX IDX_D8698A762DFF238F (absence_id), INDEX IDX_D8698A765200282E (formation_id), INDEX IDX_D8698A7659A52623 (element_variable_id), INDEX IDX_D8698A76C2785B1 (visite_medicale_id), INDEX IDX_D8698A76C69DE5E5 (validated_by_id), INDEX IDX_D8698A76A2B28FE8 (uploaded_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE element_variable (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, consolidation_id INT DEFAULT NULL, validated_by_id INT DEFAULT NULL, label VARCHAR(255) NOT NULL, amount NUMERIC(10, 2) NOT NULL, period VARCHAR(7) NOT NULL, description LONGTEXT DEFAULT NULL, category VARCHAR(50) DEFAULT \'prime\' NOT NULL, status VARCHAR(20) DEFAULT \'draft\' NOT NULL, validated_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_A0BCF920A76ED395 (user_id), INDEX IDX_A0BCF920F48F4028 (consolidation_id), INDEX IDX_A0BCF920C69DE5E5 (validated_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE formation (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, title VARCHAR(255) NOT NULL, organization VARCHAR(255) DEFAULT NULL, hours NUMERIC(5, 2) DEFAULT NULL, completed_at DATE DEFAULT NULL, renewal_at DATE DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_404021BFA76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE health (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, mutuelle_enabled TINYINT(1) DEFAULT 0 NOT NULL, mutuelle_nom VARCHAR(255) DEFAULT NULL, mutuelle_formule VARCHAR(255) DEFAULT NULL, mutuelle_date_fin DATE DEFAULT NULL, prevoyance_enabled TINYINT(1) DEFAULT 0 NOT NULL, prevoyance_nom VARCHAR(255) DEFAULT NULL, UNIQUE INDEX UNIQ_CEDA2313A76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE invitation (id INT AUTO_INCREMENT NOT NULL, villa_id INT DEFAULT NULL, user_id INT DEFAULT NULL, email VARCHAR(180) NOT NULL, first_name VARCHAR(100) NOT NULL, last_name VARCHAR(100) NOT NULL, position VARCHAR(100) DEFAULT NULL, color VARCHAR(7) DEFAULT NULL, token VARCHAR(64) NOT NULL, expires_at DATETIME NOT NULL, used_at DATETIME DEFAULT NULL, status VARCHAR(20) DEFAULT \'pending\' NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, error_message LONGTEXT DEFAULT NULL, skip_onboarding TINYINT(1) DEFAULT 0 NOT NULL, UNIQUE INDEX UNIQ_F11D61A25F37A13B (token), INDEX IDX_F11D61A2285D9761 (villa_id), INDEX IDX_F11D61A2A76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE jour_chome (id INT AUTO_INCREMENT NOT NULL, educateur_id INT NOT NULL, created_by_id INT DEFAULT NULL, date DATE NOT NULL, notes LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, INDEX IDX_4A0A883F6BFC1A0E (educateur_id), INDEX IDX_4A0A883FB03A8386 (created_by_id), UNIQUE INDEX unique_educateur_date (educateur_id, date), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE message_interne (id INT AUTO_INCREMENT NOT NULL, expediteur_id INT NOT NULL, roles_cible JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', sujet VARCHAR(255) NOT NULL, contenu LONGTEXT NOT NULL, date_envoi DATETIME NOT NULL, pieces_jointes JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', lu_par JSON NOT NULL COMMENT \'(DC2Type:json)\', created_at DATETIME NOT NULL, INDEX idx_message_expediteur (expediteur_id), INDEX idx_message_date (date_envoi), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE message_interne_destinataires (message_interne_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_378918B8918DB3B8 (message_interne_id), INDEX IDX_378918B8A76ED395 (user_id), PRIMARY KEY(message_interne_id, user_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE notification (id INT AUTO_INCREMENT NOT NULL, cible_user_id INT DEFAULT NULL, type VARCHAR(20) NOT NULL, titre VARCHAR(255) NOT NULL, message LONGTEXT NOT NULL, lien VARCHAR(500) DEFAULT NULL, roles_cible JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', date_envoi DATETIME NOT NULL, lu TINYINT(1) DEFAULT 0 NOT NULL, lu_at DATETIME DEFAULT NULL, source_event VARCHAR(50) DEFAULT NULL, source_entity_id INT DEFAULT NULL, created_at DATETIME NOT NULL, INDEX idx_notification_user (cible_user_id), INDEX idx_notification_date (date_envoi), INDEX idx_notification_lu (lu), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE planning_month (id INT AUTO_INCREMENT NOT NULL, villa_id INT NOT NULL, valide_par_id INT DEFAULT NULL, annee INT NOT NULL, mois INT NOT NULL, statut VARCHAR(20) NOT NULL, date_validation DATETIME DEFAULT NULL, INDEX IDX_1F1AAA99285D9761 (villa_id), INDEX IDX_1F1AAA996AF12ED9 (valide_par_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE profile_update_request (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, processed_by_id INT DEFAULT NULL, requested_data JSON NOT NULL COMMENT \'(DC2Type:json)\', status VARCHAR(20) DEFAULT \'pending\' NOT NULL, reason LONGTEXT DEFAULT NULL, requested_at DATETIME NOT NULL, processed_at DATETIME DEFAULT NULL, INDEX IDX_E76C5773A76ED395 (user_id), INDEX IDX_E76C57732FFD4FD3 (processed_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE rendez_vous (id INT AUTO_INCREMENT NOT NULL, created_by_id INT NOT NULL, organizer_id INT NOT NULL, titre VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, start_at DATETIME NOT NULL, end_at DATETIME NOT NULL, type VARCHAR(20) NOT NULL, impact_garde TINYINT(1) NOT NULL, statut VARCHAR(20) NOT NULL, couleur VARCHAR(7) DEFAULT NULL, duration_minutes INT DEFAULT NULL, subject VARCHAR(255) NOT NULL, location VARCHAR(255) DEFAULT NULL, refusal_reason LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_65E8AA0AB03A8386 (created_by_id), INDEX IDX_65E8AA0A876C4DDA (organizer_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE rendez_vous_user (rendez_vous_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_7AB596891EF7EAA (rendez_vous_id), INDEX IDX_7AB5968A76ED395 (user_id), PRIMARY KEY(rendez_vous_id, user_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE squelette_garde (id INT AUTO_INCREMENT NOT NULL, created_by_id INT DEFAULT NULL, updated_by_id INT DEFAULT NULL, nom VARCHAR(100) NOT NULL, description LONGTEXT DEFAULT NULL, configuration LONGTEXT NOT NULL, nombre_utilisations INT DEFAULT 0 NOT NULL, derniere_utilisation DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_AF0F96E7B03A8386 (created_by_id), INDEX IDX_AF0F96E7896DBBDE (updated_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE template_contrat (id INT AUTO_INCREMENT NOT NULL, created_by_id INT DEFAULT NULL, modified_by_id INT DEFAULT NULL, name VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, content_html LONGTEXT NOT NULL, active TINYINT(1) DEFAULT 1 NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_338CE2AAB03A8386 (created_by_id), INDEX IDX_338CE2AA99049ECE (modified_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE type_absence (id INT AUTO_INCREMENT NOT NULL, code VARCHAR(20) NOT NULL, label VARCHAR(100) NOT NULL, affects_planning TINYINT(1) DEFAULT 0 NOT NULL, deduct_from_counter TINYINT(1) DEFAULT 0 NOT NULL, requires_justification TINYINT(1) DEFAULT 0 NOT NULL, justification_deadline_days INT DEFAULT NULL, document_type VARCHAR(50) DEFAULT NULL, active TINYINT(1) DEFAULT 1 NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_5E7B8F3C77153098 (code), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE user (id INT AUTO_INCREMENT NOT NULL, villa_id INT DEFAULT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL COMMENT \'(DC2Type:json)\', password VARCHAR(255) NOT NULL, first_name VARCHAR(100) NOT NULL, last_name VARCHAR(100) NOT NULL, phone VARCHAR(20) DEFAULT NULL, address LONGTEXT DEFAULT NULL, status VARCHAR(20) DEFAULT \'invited\' NOT NULL, position VARCHAR(100) DEFAULT NULL, family_status VARCHAR(50) DEFAULT NULL, children INT DEFAULT NULL, iban VARCHAR(34) DEFAULT NULL, bic VARCHAR(11) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, cgu_accepted_at DATETIME DEFAULT NULL, submitted_at DATETIME DEFAULT NULL, matricule VARCHAR(20) DEFAULT NULL, hiring_date DATE DEFAULT NULL, color VARCHAR(7) DEFAULT NULL, force_password_change TINYINT(1) DEFAULT 0 NOT NULL, UNIQUE INDEX UNIQ_8D93D64912B2DC9C (matricule), INDEX IDX_8D93D649285D9761 (villa_id), UNIQUE INDEX UNIQ_IDENTIFIER_EMAIL (email), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE villa (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) NOT NULL, color VARCHAR(7) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE visite_medicale (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, appointment_id INT DEFAULT NULL, type VARCHAR(50) NOT NULL, status VARCHAR(50) NOT NULL, visit_date DATE DEFAULT NULL, expiry_date DATE DEFAULT NULL, medical_organization VARCHAR(255) DEFAULT NULL, aptitude VARCHAR(50) DEFAULT NULL, observations LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_B6D49D3FA76ED395 (user_id), UNIQUE INDEX UNIQ_B6D49D3FE5B533F9 (appointment_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', available_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', delivered_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_75EA56E0FB7336F0 (queue_name), INDEX IDX_75EA56E0E3BD61CE (available_at), INDEX IDX_75EA56E016BA31DB (delivered_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE absence ADD CONSTRAINT FK_765AE0C9A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE absence ADD CONSTRAINT FK_765AE0C9CCAA91B FOREIGN KEY (absence_type_id) REFERENCES type_absence (id)');
        $this->addSql('ALTER TABLE absence ADD CONSTRAINT FK_765AE0C9C69DE5E5 FOREIGN KEY (validated_by_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE affectation ADD CONSTRAINT FK_F4DD61D3276A1453 FOREIGN KEY (planning_mois_id) REFERENCES planning_month (id)');
        $this->addSql('ALTER TABLE affectation ADD CONSTRAINT FK_F4DD61D3A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE affectation ADD CONSTRAINT FK_F4DD61D3285D9761 FOREIGN KEY (villa_id) REFERENCES villa (id)');
        $this->addSql('ALTER TABLE annonce_interne ADD CONSTRAINT FK_DAAA0CFC801A2092 FOREIGN KEY (publie_par_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE appointment_participants ADD CONSTRAINT FK_88AB679FE5B533F9 FOREIGN KEY (appointment_id) REFERENCES rendez_vous (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE appointment_participants ADD CONSTRAINT FK_88AB679FA76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE astreinte ADD CONSTRAINT FK_F23DC0736BFC1A0E FOREIGN KEY (educateur_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE astreinte ADD CONSTRAINT FK_F23DC073B03A8386 FOREIGN KEY (created_by_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE astreinte ADD CONSTRAINT FK_F23DC073896DBBDE FOREIGN KEY (updated_by_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE compteur_absence ADD CONSTRAINT FK_C49D8C0BA76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE compteur_absence ADD CONSTRAINT FK_C49D8C0BCCAA91B FOREIGN KEY (absence_type_id) REFERENCES type_absence (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE compteur_cp ADD CONSTRAINT FK_4A0EA4E7A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE compteur_jours_annuels ADD CONSTRAINT FK_5FD9D8C0A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE consolidation_paie ADD CONSTRAINT FK_C865968DA76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE consolidation_paie ADD CONSTRAINT FK_C865968DC69DE5E5 FOREIGN KEY (validated_by_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE consolidation_paie_history ADD CONSTRAINT FK_96887FC8F48F4028 FOREIGN KEY (consolidation_id) REFERENCES consolidation_paie (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE consolidation_paie_history ADD CONSTRAINT FK_96887FC899049ECE FOREIGN KEY (modified_by_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE contract ADD CONSTRAINT FK_E98F2859A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE contract ADD CONSTRAINT FK_E98F2859285D9761 FOREIGN KEY (villa_id) REFERENCES villa (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE contract ADD CONSTRAINT FK_E98F28594E5AF28D FOREIGN KEY (parent_contract_id) REFERENCES contract (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE contract ADD CONSTRAINT FK_E98F2859B03A8386 FOREIGN KEY (created_by_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE contract ADD CONSTRAINT FK_E98F28595DA0FB8 FOREIGN KEY (template_id) REFERENCES template_contrat (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE contract ADD CONSTRAINT FK_E98F2859C69DE5E5 FOREIGN KEY (validated_by_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE document ADD CONSTRAINT FK_D8698A76A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE document ADD CONSTRAINT FK_D8698A762576E0FD FOREIGN KEY (contract_id) REFERENCES contract (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE document ADD CONSTRAINT FK_D8698A762DFF238F FOREIGN KEY (absence_id) REFERENCES absence (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE document ADD CONSTRAINT FK_D8698A765200282E FOREIGN KEY (formation_id) REFERENCES formation (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE document ADD CONSTRAINT FK_D8698A7659A52623 FOREIGN KEY (element_variable_id) REFERENCES element_variable (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE document ADD CONSTRAINT FK_D8698A76C2785B1 FOREIGN KEY (visite_medicale_id) REFERENCES visite_medicale (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE document ADD CONSTRAINT FK_D8698A76C69DE5E5 FOREIGN KEY (validated_by_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE document ADD CONSTRAINT FK_D8698A76A2B28FE8 FOREIGN KEY (uploaded_by_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE element_variable ADD CONSTRAINT FK_A0BCF920A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE element_variable ADD CONSTRAINT FK_A0BCF920F48F4028 FOREIGN KEY (consolidation_id) REFERENCES consolidation_paie (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE element_variable ADD CONSTRAINT FK_A0BCF920C69DE5E5 FOREIGN KEY (validated_by_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE formation ADD CONSTRAINT FK_404021BFA76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE health ADD CONSTRAINT FK_CEDA2313A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE invitation ADD CONSTRAINT FK_F11D61A2285D9761 FOREIGN KEY (villa_id) REFERENCES villa (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE invitation ADD CONSTRAINT FK_F11D61A2A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE jour_chome ADD CONSTRAINT FK_4A0A883F6BFC1A0E FOREIGN KEY (educateur_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE jour_chome ADD CONSTRAINT FK_4A0A883FB03A8386 FOREIGN KEY (created_by_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE message_interne ADD CONSTRAINT FK_B04DAC9010335F61 FOREIGN KEY (expediteur_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE message_interne_destinataires ADD CONSTRAINT FK_378918B8918DB3B8 FOREIGN KEY (message_interne_id) REFERENCES message_interne (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE message_interne_destinataires ADD CONSTRAINT FK_378918B8A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE notification ADD CONSTRAINT FK_BF5476CA6A2544E6 FOREIGN KEY (cible_user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE planning_month ADD CONSTRAINT FK_1F1AAA99285D9761 FOREIGN KEY (villa_id) REFERENCES villa (id)');
        $this->addSql('ALTER TABLE planning_month ADD CONSTRAINT FK_1F1AAA996AF12ED9 FOREIGN KEY (valide_par_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE profile_update_request ADD CONSTRAINT FK_E76C5773A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE profile_update_request ADD CONSTRAINT FK_E76C57732FFD4FD3 FOREIGN KEY (processed_by_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE rendez_vous ADD CONSTRAINT FK_65E8AA0AB03A8386 FOREIGN KEY (created_by_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE rendez_vous ADD CONSTRAINT FK_65E8AA0A876C4DDA FOREIGN KEY (organizer_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE rendez_vous_user ADD CONSTRAINT FK_7AB596891EF7EAA FOREIGN KEY (rendez_vous_id) REFERENCES rendez_vous (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE rendez_vous_user ADD CONSTRAINT FK_7AB5968A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE squelette_garde ADD CONSTRAINT FK_AF0F96E7B03A8386 FOREIGN KEY (created_by_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE squelette_garde ADD CONSTRAINT FK_AF0F96E7896DBBDE FOREIGN KEY (updated_by_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE template_contrat ADD CONSTRAINT FK_338CE2AAB03A8386 FOREIGN KEY (created_by_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE template_contrat ADD CONSTRAINT FK_338CE2AA99049ECE FOREIGN KEY (modified_by_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE user ADD CONSTRAINT FK_8D93D649285D9761 FOREIGN KEY (villa_id) REFERENCES villa (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE visite_medicale ADD CONSTRAINT FK_B6D49D3FA76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE visite_medicale ADD CONSTRAINT FK_B6D49D3FE5B533F9 FOREIGN KEY (appointment_id) REFERENCES rendez_vous (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE absence DROP FOREIGN KEY FK_765AE0C9A76ED395');
        $this->addSql('ALTER TABLE absence DROP FOREIGN KEY FK_765AE0C9CCAA91B');
        $this->addSql('ALTER TABLE absence DROP FOREIGN KEY FK_765AE0C9C69DE5E5');
        $this->addSql('ALTER TABLE affectation DROP FOREIGN KEY FK_F4DD61D3276A1453');
        $this->addSql('ALTER TABLE affectation DROP FOREIGN KEY FK_F4DD61D3A76ED395');
        $this->addSql('ALTER TABLE affectation DROP FOREIGN KEY FK_F4DD61D3285D9761');
        $this->addSql('ALTER TABLE annonce_interne DROP FOREIGN KEY FK_DAAA0CFC801A2092');
        $this->addSql('ALTER TABLE appointment_participants DROP FOREIGN KEY FK_88AB679FE5B533F9');
        $this->addSql('ALTER TABLE appointment_participants DROP FOREIGN KEY FK_88AB679FA76ED395');
        $this->addSql('ALTER TABLE astreinte DROP FOREIGN KEY FK_F23DC0736BFC1A0E');
        $this->addSql('ALTER TABLE astreinte DROP FOREIGN KEY FK_F23DC073B03A8386');
        $this->addSql('ALTER TABLE astreinte DROP FOREIGN KEY FK_F23DC073896DBBDE');
        $this->addSql('ALTER TABLE compteur_absence DROP FOREIGN KEY FK_C49D8C0BA76ED395');
        $this->addSql('ALTER TABLE compteur_absence DROP FOREIGN KEY FK_C49D8C0BCCAA91B');
        $this->addSql('ALTER TABLE compteur_cp DROP FOREIGN KEY FK_4A0EA4E7A76ED395');
        $this->addSql('ALTER TABLE compteur_jours_annuels DROP FOREIGN KEY FK_5FD9D8C0A76ED395');
        $this->addSql('ALTER TABLE consolidation_paie DROP FOREIGN KEY FK_C865968DA76ED395');
        $this->addSql('ALTER TABLE consolidation_paie DROP FOREIGN KEY FK_C865968DC69DE5E5');
        $this->addSql('ALTER TABLE consolidation_paie_history DROP FOREIGN KEY FK_96887FC8F48F4028');
        $this->addSql('ALTER TABLE consolidation_paie_history DROP FOREIGN KEY FK_96887FC899049ECE');
        $this->addSql('ALTER TABLE contract DROP FOREIGN KEY FK_E98F2859A76ED395');
        $this->addSql('ALTER TABLE contract DROP FOREIGN KEY FK_E98F2859285D9761');
        $this->addSql('ALTER TABLE contract DROP FOREIGN KEY FK_E98F28594E5AF28D');
        $this->addSql('ALTER TABLE contract DROP FOREIGN KEY FK_E98F2859B03A8386');
        $this->addSql('ALTER TABLE contract DROP FOREIGN KEY FK_E98F28595DA0FB8');
        $this->addSql('ALTER TABLE contract DROP FOREIGN KEY FK_E98F2859C69DE5E5');
        $this->addSql('ALTER TABLE document DROP FOREIGN KEY FK_D8698A76A76ED395');
        $this->addSql('ALTER TABLE document DROP FOREIGN KEY FK_D8698A762576E0FD');
        $this->addSql('ALTER TABLE document DROP FOREIGN KEY FK_D8698A762DFF238F');
        $this->addSql('ALTER TABLE document DROP FOREIGN KEY FK_D8698A765200282E');
        $this->addSql('ALTER TABLE document DROP FOREIGN KEY FK_D8698A7659A52623');
        $this->addSql('ALTER TABLE document DROP FOREIGN KEY FK_D8698A76C2785B1');
        $this->addSql('ALTER TABLE document DROP FOREIGN KEY FK_D8698A76C69DE5E5');
        $this->addSql('ALTER TABLE document DROP FOREIGN KEY FK_D8698A76A2B28FE8');
        $this->addSql('ALTER TABLE element_variable DROP FOREIGN KEY FK_A0BCF920A76ED395');
        $this->addSql('ALTER TABLE element_variable DROP FOREIGN KEY FK_A0BCF920F48F4028');
        $this->addSql('ALTER TABLE element_variable DROP FOREIGN KEY FK_A0BCF920C69DE5E5');
        $this->addSql('ALTER TABLE formation DROP FOREIGN KEY FK_404021BFA76ED395');
        $this->addSql('ALTER TABLE health DROP FOREIGN KEY FK_CEDA2313A76ED395');
        $this->addSql('ALTER TABLE invitation DROP FOREIGN KEY FK_F11D61A2285D9761');
        $this->addSql('ALTER TABLE invitation DROP FOREIGN KEY FK_F11D61A2A76ED395');
        $this->addSql('ALTER TABLE jour_chome DROP FOREIGN KEY FK_4A0A883F6BFC1A0E');
        $this->addSql('ALTER TABLE jour_chome DROP FOREIGN KEY FK_4A0A883FB03A8386');
        $this->addSql('ALTER TABLE message_interne DROP FOREIGN KEY FK_B04DAC9010335F61');
        $this->addSql('ALTER TABLE message_interne_destinataires DROP FOREIGN KEY FK_378918B8918DB3B8');
        $this->addSql('ALTER TABLE message_interne_destinataires DROP FOREIGN KEY FK_378918B8A76ED395');
        $this->addSql('ALTER TABLE notification DROP FOREIGN KEY FK_BF5476CA6A2544E6');
        $this->addSql('ALTER TABLE planning_month DROP FOREIGN KEY FK_1F1AAA99285D9761');
        $this->addSql('ALTER TABLE planning_month DROP FOREIGN KEY FK_1F1AAA996AF12ED9');
        $this->addSql('ALTER TABLE profile_update_request DROP FOREIGN KEY FK_E76C5773A76ED395');
        $this->addSql('ALTER TABLE profile_update_request DROP FOREIGN KEY FK_E76C57732FFD4FD3');
        $this->addSql('ALTER TABLE rendez_vous DROP FOREIGN KEY FK_65E8AA0AB03A8386');
        $this->addSql('ALTER TABLE rendez_vous DROP FOREIGN KEY FK_65E8AA0A876C4DDA');
        $this->addSql('ALTER TABLE rendez_vous_user DROP FOREIGN KEY FK_7AB596891EF7EAA');
        $this->addSql('ALTER TABLE rendez_vous_user DROP FOREIGN KEY FK_7AB5968A76ED395');
        $this->addSql('ALTER TABLE squelette_garde DROP FOREIGN KEY FK_AF0F96E7B03A8386');
        $this->addSql('ALTER TABLE squelette_garde DROP FOREIGN KEY FK_AF0F96E7896DBBDE');
        $this->addSql('ALTER TABLE template_contrat DROP FOREIGN KEY FK_338CE2AAB03A8386');
        $this->addSql('ALTER TABLE template_contrat DROP FOREIGN KEY FK_338CE2AA99049ECE');
        $this->addSql('ALTER TABLE user DROP FOREIGN KEY FK_8D93D649285D9761');
        $this->addSql('ALTER TABLE visite_medicale DROP FOREIGN KEY FK_B6D49D3FA76ED395');
        $this->addSql('ALTER TABLE visite_medicale DROP FOREIGN KEY FK_B6D49D3FE5B533F9');
        $this->addSql('DROP TABLE absence');
        $this->addSql('DROP TABLE affectation');
        $this->addSql('DROP TABLE annonce_interne');
        $this->addSql('DROP TABLE appointment_participants');
        $this->addSql('DROP TABLE astreinte');
        $this->addSql('DROP TABLE compteur_absence');
        $this->addSql('DROP TABLE compteur_cp');
        $this->addSql('DROP TABLE compteur_jours_annuels');
        $this->addSql('DROP TABLE consolidation_paie');
        $this->addSql('DROP TABLE consolidation_paie_history');
        $this->addSql('DROP TABLE contract');
        $this->addSql('DROP TABLE document');
        $this->addSql('DROP TABLE element_variable');
        $this->addSql('DROP TABLE formation');
        $this->addSql('DROP TABLE health');
        $this->addSql('DROP TABLE invitation');
        $this->addSql('DROP TABLE jour_chome');
        $this->addSql('DROP TABLE message_interne');
        $this->addSql('DROP TABLE message_interne_destinataires');
        $this->addSql('DROP TABLE notification');
        $this->addSql('DROP TABLE planning_month');
        $this->addSql('DROP TABLE profile_update_request');
        $this->addSql('DROP TABLE rendez_vous');
        $this->addSql('DROP TABLE rendez_vous_user');
        $this->addSql('DROP TABLE squelette_garde');
        $this->addSql('DROP TABLE template_contrat');
        $this->addSql('DROP TABLE type_absence');
        $this->addSql('DROP TABLE user');
        $this->addSql('DROP TABLE villa');
        $this->addSql('DROP TABLE visite_medicale');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
