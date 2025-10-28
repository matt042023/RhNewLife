<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add weekly hours column to contract table to align with entity.
 */
final class Version20251028120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add nullable weekly_hours column to contract';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE contract ADD weekly_hours NUMERIC(5, 2) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE contract DROP weekly_hours');
    }
}
