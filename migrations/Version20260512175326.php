<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260512175326 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Custom migration strictly for Resources and ResourceAssignment entities missing columns.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE resources CHANGE resource_name resource_name VARCHAR(255) NOT NULL, CHANGE image_path image_path VARCHAR(255) DEFAULT NULL');
        
        $this->addSql('ALTER TABLE resource_assignment ADD returned TINYINT NOT NULL, CHANGE project_code project_code VARCHAR(255) NOT NULL, CHANGE client_code client_code VARCHAR(255) DEFAULT NULL, CHANGE total_cost total_cost NUMERIC(10, 2) DEFAULT NULL, CHANGE status status VARCHAR(255) DEFAULT NULL, CHANGE penalty_days_applied penalty_days_applied INT NOT NULL, CHANGE bonus_applied bonus_applied TINYINT NOT NULL, CHANGE user_id user_id INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
