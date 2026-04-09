<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260405000847 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE conversation_participants CHANGE conversation_id conversation_id INT NOT NULL, CHANGE role role VARCHAR(255) NOT NULL, CHANGE nickname nickname VARCHAR(255) DEFAULT NULL, CHANGE joined_at joined_at DATETIME NOT NULL, CHANGE last_read_message_id last_read_message_id INT DEFAULT NULL');

        $this->addSql('ALTER TABLE formation CHANGE titre titre VARCHAR(255) DEFAULT NULL, CHANGE description description LONGTEXT DEFAULT NULL');

        $this->addSql('ALTER TABLE message_attachments CHANGE id id INT AUTO_INCREMENT NOT NULL, CHANGE message_id message_id INT NOT NULL, CHANGE mime_type mime_type VARCHAR(255) NOT NULL, CHANGE size_bytes size_bytes INT NOT NULL, CHANGE created_at created_at DATETIME DEFAULT NULL');

        $this->addSql('ALTER TABLE messages CHANGE id id INT AUTO_INCREMENT NOT NULL, CHANGE conversation_id conversation_id INT NOT NULL, CHANGE body body LONGTEXT NOT NULL, CHANGE kind kind VARCHAR(255) NOT NULL, CHANGE created_at created_at DATETIME NOT NULL');

        $this->addSql('ALTER TABLE participer CHANGE date_inscription date_inscription DATETIME DEFAULT NULL, CHANGE progression progression INT DEFAULT NULL, CHANGE statut statut VARCHAR(255) DEFAULT NULL');

        $this->addSql('ALTER TABLE project_budget CHANGE id id INT AUTO_INCREMENT NOT NULL, CHANGE total_budget total_budget NUMERIC(10, 2) NOT NULL, CHANGE actualSpend actualSpend NUMERIC(10, 2) NOT NULL, CHANGE status status VARCHAR(255) DEFAULT NULL, CHANGE projectId projectId INT NOT NULL');

        $this->addSql('ALTER TABLE projects CHANGE id id INT AUTO_INCREMENT NOT NULL, CHANGE description description LONGTEXT DEFAULT NULL, CHANGE budget budget NUMERIC(10, 2) DEFAULT NULL, CHANGE progress progress INT DEFAULT NULL, CHANGE created_at created_at DATETIME DEFAULT NULL, CHANGE updated_at updated_at DATETIME DEFAULT NULL');

        $this->addSql('ALTER TABLE quiz CHANGE question question LONGTEXT DEFAULT NULL, CHANGE correct correct INT NOT NULL');

        // idRec n'existe plus sur cette base, donc on ne le modifie pas
        $this->addSql('ALTER TABLE reclamation CHANGE categorie categorie VARCHAR(255) DEFAULT NULL, CHANGE statut statut VARCHAR(255) DEFAULT NULL, CHANGE date date VARCHAR(255) DEFAULT NULL');

        $this->addSql('ALTER TABLE resource_assignment CHANGE project_code project_code VARCHAR(255) NOT NULL, CHANGE client_code client_code VARCHAR(255) DEFAULT NULL, CHANGE total_cost total_cost NUMERIC(10, 2) DEFAULT NULL, CHANGE status status VARCHAR(255) DEFAULT NULL, CHANGE penalty_days_applied penalty_days_applied INT NOT NULL, CHANGE bonus_applied bonus_applied TINYINT NOT NULL, CHANGE user_id user_id INT DEFAULT NULL');

        $this->addSql('ALTER TABLE resources CHANGE resource_code resource_code VARCHAR(255) NOT NULL, CHANGE resource_name resource_name VARCHAR(255) NOT NULL, CHANGE resource_type resource_type VARCHAR(255) NOT NULL, CHANGE unit_cost unit_cost NUMERIC(10, 2) NOT NULL, CHANGE status status VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // down() intentionally left empty
    }
}
