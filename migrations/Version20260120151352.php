<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260120151352 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE compteur_cp CHANGE solde_initial solde_initial DOUBLE PRECISION NOT NULL, CHANGE acquis acquis DOUBLE PRECISION NOT NULL, CHANGE pris pris DOUBLE PRECISION NOT NULL, CHANGE ajustement_admin ajustement_admin DOUBLE PRECISION NOT NULL');
        $this->addSql('ALTER TABLE compteur_jours_annuels CHANGE jours_alloues jours_alloues DOUBLE PRECISION NOT NULL, CHANGE jours_consommes jours_consommes DOUBLE PRECISION NOT NULL');
        $this->addSql('ALTER TABLE consolidation_paie CHANGE jours_travailes jours_travailes DOUBLE PRECISION NOT NULL, CHANGE jours_evenements jours_evenements DOUBLE PRECISION NOT NULL, CHANGE cp_acquis cp_acquis DOUBLE PRECISION NOT NULL, CHANGE cp_pris cp_pris DOUBLE PRECISION NOT NULL, CHANGE cp_solde_debut cp_solde_debut DOUBLE PRECISION NOT NULL, CHANGE cp_solde_fin cp_solde_fin DOUBLE PRECISION NOT NULL, CHANGE total_variables total_variables DOUBLE PRECISION NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE compteur_cp CHANGE solde_initial solde_initial NUMERIC(5, 2) NOT NULL, CHANGE acquis acquis NUMERIC(5, 2) NOT NULL, CHANGE pris pris NUMERIC(5, 2) NOT NULL, CHANGE ajustement_admin ajustement_admin NUMERIC(5, 2) NOT NULL');
        $this->addSql('ALTER TABLE compteur_jours_annuels CHANGE jours_alloues jours_alloues NUMERIC(5, 2) NOT NULL, CHANGE jours_consommes jours_consommes NUMERIC(5, 2) NOT NULL');
        $this->addSql('ALTER TABLE consolidation_paie CHANGE jours_travailes jours_travailes NUMERIC(5, 2) NOT NULL, CHANGE jours_evenements jours_evenements NUMERIC(5, 2) NOT NULL, CHANGE cp_acquis cp_acquis NUMERIC(5, 2) NOT NULL, CHANGE cp_pris cp_pris NUMERIC(5, 2) NOT NULL, CHANGE cp_solde_debut cp_solde_debut NUMERIC(5, 2) NOT NULL, CHANGE cp_solde_fin cp_solde_fin NUMERIC(5, 2) NOT NULL, CHANGE total_variables total_variables NUMERIC(10, 2) NOT NULL');
    }
}
