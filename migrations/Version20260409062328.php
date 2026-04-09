<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Clean Migration: Updating transaction description from INT to VARCHAR
 */
final class Version20260409062328 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Update transaction description type and fix reference index';
    }

    public function up(Schema $schema): void
    {
        // 1. Update the description column type in the transaction table
        // We only change the description column, leaving IDs and Foreign Keys untouched
        $this->addSql('ALTER TABLE transaction CHANGE description description VARCHAR(255) DEFAULT NULL');

        // 2. Fix the index on the reference column
        $this->addSql('ALTER TABLE transaction DROP INDEX reference, ADD INDEX reference (reference)');
    }

    public function down(Schema $schema): void
    {
        // Reverting description back to INT
        $this->addSql('ALTER TABLE transaction CHANGE description description INT NOT NULL');
        
        // Reverting index to UNIQUE
        $this->addSql('ALTER TABLE transaction DROP INDEX reference, ADD UNIQUE INDEX reference (reference)');
    }
}
