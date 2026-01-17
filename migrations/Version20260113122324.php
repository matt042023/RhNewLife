<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260113122324 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rendre villa_id nullable pour les renforts + ajouter contrainte CHECK permissive';
    }

    public function up(Schema $schema): void
    {
        // Rendre villa_id nullable pour permettre les renforts sans villa
        $this->addSql('ALTER TABLE affectation CHANGE villa_id villa_id INT DEFAULT NULL');

        // Ajouter une contrainte CHECK permissive pour validation métier
        // - Gardes principales (24h/48h) DOIVENT avoir une villa
        // - Renforts PEUVENT avoir une villa (villa-spécifique) ou NULL (centre-complet)
        // - Type "autre" reste flexible
        $this->addSql('
            ALTER TABLE affectation ADD CONSTRAINT check_villa_required_for_main_shifts
            CHECK (
                (type IN ("garde_24h", "garde_48h") AND villa_id IS NOT NULL) OR
                (type = "renfort") OR
                (type = "autre")
            )
        ');
    }

    public function down(Schema $schema): void
    {
        // Supprimer la contrainte CHECK
        $this->addSql('ALTER TABLE affectation DROP CONSTRAINT IF EXISTS check_villa_required_for_main_shifts');

        // Restaurer villa_id NOT NULL (attention: échouera si des renforts ont villa_id NULL)
        $this->addSql('ALTER TABLE affectation CHANGE villa_id villa_id INT NOT NULL');
    }
}
