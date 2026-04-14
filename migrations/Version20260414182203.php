<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260414182203 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE expense_draft (id INT AUTO_INCREMENT NOT NULL, supabase_id VARCHAR(255) DEFAULT NULL, amount DOUBLE PRECISION NOT NULL, description LONGTEXT NOT NULL, status VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, category VARCHAR(255) NOT NULL, created_by_id INT NOT NULL, project_budget_related_id BIGINT NOT NULL, INDEX IDX_E57431AEB03A8386 (created_by_id), INDEX IDX_E57431AEA3B6537E (project_budget_related_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE expense_draft ADD CONSTRAINT FK_E57431AEA3B6537E FOREIGN KEY (project_budget_related_id) REFERENCES project_budget (id)');        $this->addSql('ALTER TABLE project_budget ADD CONSTRAINT FK_64561C316C9360F7 FOREIGN KEY (projectId) REFERENCES projects (id)');
        $this->addSql('CREATE INDEX fk_pro_id ON project_budget (projectId)');        $this->addSql('ALTER TABLE quiz CHANGE question question LONGTEXT NOT NULL, CHANGE r1 r1 VARCHAR(255) NOT NULL, CHANGE r2 r2 VARCHAR(255) NOT NULL, CHANGE r3 r3 VARCHAR(255) NOT NULL, CHANGE formation_id formation_id INT NOT NULL, CHANGE correct correct INT NOT NULL');        $this->addSql('ALTER TABLE reclamation CHANGE categorie categorie VARCHAR(255) DEFAULT NULL, CHANGE statut statut VARCHAR(255) DEFAULT NULL, CHANGE date date VARCHAR(255) DEFAULT NULL');        $this->addSql('ALTER TABLE resource_assignment RENAME INDEX fk_assignment_user TO IDX_50DD033CA76ED395');        $this->addSql('ALTER TABLE resources CHANGE resource_name resource_name VARCHAR(255) NOT NULL, CHANGE resource_type resource_type VARCHAR(50) NOT NULL, CHANGE unit_cost unit_cost NUMERIC(10, 2) NOT NULL, CHANGE status status VARCHAR(50) DEFAULT NULL');
        $this->addSql('ALTER TABLE resultat ADD user_id INT NOT NULL, CHANGE date_passage date_passage DATETIME NOT NULL');
        $this->addSql('CREATE INDEX IDX_E7DB5DE2A76ED395 ON resultat (user_id)');        $this->addSql('ALTER TABLE tasks CHANGE id id INT AUTO_INCREMENT NOT NULL, CHANGE description description LONGTEXT DEFAULT NULL, CHANGE status status VARCHAR(255) DEFAULT NULL, CHANGE priority priority VARCHAR(255) DEFAULT NULL, CHANGE created_at created_at DATETIME DEFAULT NULL, CHANGE updated_at updated_at DATETIME DEFAULT NULL, CHANGE project_id project_id INT NOT NULL');        $this->addSql('ALTER TABLE transaction ADD CONSTRAINT FK_723705D12797E36F FOREIGN KEY (project_budget_id) REFERENCES project_budget (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE expense_draft DROP FOREIGN KEY FK_E57431AEB03A8386');
        $this->addSql('ALTER TABLE expense_draft DROP FOREIGN KEY FK_E57431AEA3B6537E');
        $this->addSql('DROP TABLE expense_draft');        $this->addSql('ALTER TABLE project_budget DROP FOREIGN KEY FK_64561C316C9360F7');        $this->addSql('ALTER TABLE project_budget CHANGE id id BIGINT AUTO_INCREMENT NOT NULL, CHANGE total_budget total_budget NUMERIC(15, 2) NOT NULL, CHANGE actualSpend actualSpend NUMERIC(15, 2) NOT NULL, CHANGE status status VARCHAR(50) DEFAULT \'PENDING\', CHANGE projectId projectId BIGINT UNSIGNED NOT NULL');
        $this->addSql('ALTER TABLE projects CHANGE id id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL, CHANGE description description TEXT DEFAULT NULL, CHANGE budget budget NUMERIC(12, 2) DEFAULT NULL, CHANGE progress progress INT DEFAULT 0, CHANGE created_at created_at DATETIME DEFAULT CURRENT_TIMESTAMP, CHANGE updated_at updated_at DATETIME DEFAULT CURRENT_TIMESTAMP');        $this->addSql('ALTER TABLE quiz CHANGE question question TEXT DEFAULT NULL, CHANGE r1 r1 VARCHAR(255) DEFAULT NULL, CHANGE r2 r2 VARCHAR(255) DEFAULT NULL, CHANGE r3 r3 VARCHAR(255) DEFAULT NULL, CHANGE correct correct INT DEFAULT 1 NOT NULL, CHANGE formation_id formation_id INT DEFAULT NULL');        $this->addSql('ALTER TABLE reclamation CHANGE categorie categorie VARCHAR(100) DEFAULT NULL, CHANGE statut statut VARCHAR(50) DEFAULT NULL, CHANGE date date VARCHAR(50) DEFAULT NULL');        $this->addSql('ALTER TABLE resource_assignment CHANGE project_code project_code VARCHAR(50) NOT NULL, CHANGE client_code client_code VARCHAR(50) DEFAULT NULL, CHANGE total_cost total_cost NUMERIC(12, 2) DEFAULT NULL, CHANGE status status VARCHAR(50) DEFAULT \'ACTIVE\', CHANGE penalty_days_applied penalty_days_applied INT DEFAULT 0 NOT NULL, CHANGE bonus_applied bonus_applied TINYINT DEFAULT 0 NOT NULL, CHANGE user_id user_id INT NOT NULL');        $this->addSql('ALTER TABLE resources CHANGE resource_name resource_name VARCHAR(150) NOT NULL, CHANGE resource_type resource_type ENUM(\'PHYSICAL\', \'SOFTWARE\') NOT NULL, CHANGE unit_cost unit_cost NUMERIC(12, 2) NOT NULL, CHANGE status status VARCHAR(50) DEFAULT \'AVAILABLE\'');
        $this->addSql('CREATE UNIQUE INDEX resource_code ON resources (resource_code)');        $this->addSql('ALTER TABLE resultat DROP FOREIGN KEY FK_E7DB5DE2A76ED395');        $this->addSql('ALTER TABLE resultat DROP user_id, CHANGE date_passage date_passage DATETIME DEFAULT CURRENT_TIMESTAMP');        $this->addSql('ALTER TABLE tasks CHANGE id id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL, CHANGE description description TEXT DEFAULT NULL, CHANGE status status VARCHAR(50) DEFAULT NULL, CHANGE priority priority ENUM(\'LOW\', \'MEDIUM\', \'HIGH\') DEFAULT \'MEDIUM\', CHANGE created_at created_at DATETIME DEFAULT CURRENT_TIMESTAMP, CHANGE updated_at updated_at DATETIME DEFAULT CURRENT_TIMESTAMP, CHANGE project_id project_id BIGINT UNSIGNED NOT NULL');        $this->addSql('ALTER TABLE transaction CHANGE id id BIGINT AUTO_INCREMENT NOT NULL, CHANGE reference reference VARCHAR(50) DEFAULT NULL, CHANGE cost cost NUMERIC(15, 2) NOT NULL, CHANGE expense_category expense_category VARCHAR(100) DEFAULT NULL, CHANGE project_budget_id project_budget_id BIGINT NOT NULL');
        $this->addSql('ALTER TABLE transaction ADD CONSTRAINT `fk_transaction_project_budget` FOREIGN KEY (project_budget_id) REFERENCES project_budget (id) ON UPDATE CASCADE ON DELETE CASCADE');        $this->addSql('ALTER TABLE utilisateurs CHANGE nom nom VARCHAR(50) NOT NULL, CHANGE prenom prenom VARCHAR(50) NOT NULL, CHANGE email email VARCHAR(150) NOT NULL, CHANGE telephone telephone VARCHAR(20) DEFAULT NULL, CHANGE role role VARCHAR(50) NOT NULL, CHANGE departement departement VARCHAR(100) DEFAULT NULL, CHANGE statut statut VARCHAR(20) DEFAULT \'Pending\'');
    }
}
