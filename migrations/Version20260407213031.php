<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260407213031 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

        public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE budget_profile ADD base_currency VARCHAR(3) DEFAULT NULL, ADD start_date DATE DEFAULT NULL, ADD end_date DATE DEFAULT NULL, ADD status VARCHAR(50) DEFAULT \'DRAFT\' NOT NULL');
    }

        public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE budget_profile DROP base_currency, DROP start_date, DROP end_date, DROP status');
    }
}
