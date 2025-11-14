<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251114103545 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Migrate archived boolean flag to status ARCHIVED, remove archived column';
    }

    public function up(Schema $schema): void
    {
        // Step 1: Migrate data - Set status to 'archived' for all documents where archived = true
        $this->addSql("UPDATE document SET status = 'archived' WHERE archived = 1");

        // Step 2: Drop the archived column
        $this->addSql('ALTER TABLE document DROP archived');
    }

    public function down(Schema $schema): void
    {
        // Recreate archived column
        $this->addSql('ALTER TABLE document ADD archived TINYINT(1) DEFAULT 0 NOT NULL');

        // Migrate data back - Set archived = true for all documents where status = 'archived'
        $this->addSql("UPDATE document SET archived = 1 WHERE status = 'archived'");

        // Reset status to pending for archived documents (since archived won't be a valid status anymore)
        $this->addSql("UPDATE document SET status = 'pending' WHERE status = 'archived'");
    }
}
