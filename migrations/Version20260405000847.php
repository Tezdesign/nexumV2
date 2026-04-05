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
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE conversation_participants CHANGE conversation_id conversation_id INT NOT NULL, CHANGE role role VARCHAR(255) NOT NULL, CHANGE nickname nickname VARCHAR(255) DEFAULT NULL, CHANGE joined_at joined_at DATETIME NOT NULL, CHANGE last_read_message_id last_read_message_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE formation CHANGE titre titre VARCHAR(255) DEFAULT NULL, CHANGE description description LONGTEXT DEFAULT NULL');
        $this->addSql('DROP INDEX index_attachment_message ON message_attachments');
        $this->addSql('ALTER TABLE message_attachments CHANGE id id INT AUTO_INCREMENT NOT NULL, CHANGE message_id message_id INT NOT NULL, CHANGE mime_type mime_type VARCHAR(255) NOT NULL, CHANGE size_bytes size_bytes INT NOT NULL, CHANGE created_at created_at DATETIME DEFAULT NULL');
        $this->addSql('DROP INDEX ft_messages_body ON messages');
        $this->addSql('DROP INDEX idx_messages_conv_id ON messages');
        $this->addSql('DROP INDEX idx_messages_conv_time ON messages');
        $this->addSql('DROP INDEX idx_messages_sender_time ON messages');
        $this->addSql('ALTER TABLE messages CHANGE id id INT AUTO_INCREMENT NOT NULL, CHANGE conversation_id conversation_id INT NOT NULL, CHANGE body body LONGTEXT NOT NULL, CHANGE kind kind VARCHAR(255) NOT NULL, CHANGE created_at created_at DATETIME NOT NULL');
        $this->addSql('DROP INDEX user_id ON participer');
        $this->addSql('ALTER TABLE participer CHANGE date_inscription date_inscription DATETIME DEFAULT NULL, CHANGE progression progression INT DEFAULT NULL, CHANGE statut statut VARCHAR(255) DEFAULT NULL');
        $this->addSql('DROP INDEX fk_pro_id ON project_budget');
        $this->addSql('ALTER TABLE project_budget CHANGE id id INT AUTO_INCREMENT NOT NULL, CHANGE total_budget total_budget NUMERIC(10, 2) NOT NULL, CHANGE actualSpend actualSpend NUMERIC(10, 2) NOT NULL, CHANGE status status VARCHAR(255) DEFAULT NULL, CHANGE projectId projectId INT NOT NULL');
        $this->addSql('DROP INDEX idx_projects_assigned_to ON projects');
        $this->addSql('DROP INDEX idx_projects_created_by ON projects');
        $this->addSql('DROP INDEX idx_projects_end_date ON projects');
        $this->addSql('DROP INDEX idx_projects_start_date ON projects');
        $this->addSql('ALTER TABLE projects CHANGE id id INT AUTO_INCREMENT NOT NULL, CHANGE description description LONGTEXT DEFAULT NULL, CHANGE budget budget NUMERIC(10, 2) DEFAULT NULL, CHANGE progress progress INT DEFAULT NULL, CHANGE created_at created_at DATETIME DEFAULT NULL, CHANGE updated_at updated_at DATETIME DEFAULT NULL');
        $this->addSql('DROP INDEX formation_id ON quiz');
        $this->addSql('ALTER TABLE quiz CHANGE question question LONGTEXT DEFAULT NULL, CHANGE correct correct INT NOT NULL');
        $this->addSql('DROP INDEX fk_reclamation_user ON reclamation');
        $this->addSql('ALTER TABLE reclamation MODIFY idRec INT NOT NULL');
        $this->addSql('ALTER TABLE reclamation CHANGE categorie categorie VARCHAR(255) DEFAULT NULL, CHANGE statut statut VARCHAR(255) DEFAULT NULL, CHANGE date date VARCHAR(255) DEFAULT NULL, CHANGE idRec id_rec INT AUTO_INCREMENT NOT NULL, DROP PRIMARY KEY, ADD PRIMARY KEY (id_rec)');
        $this->addSql('ALTER TABLE resource_assignment DROP FOREIGN KEY `fk_assignment_user`');
        $this->addSql('DROP INDEX fk_ra_resource ON resource_assignment');
        $this->addSql('ALTER TABLE resource_assignment CHANGE project_code project_code VARCHAR(255) NOT NULL, CHANGE client_code client_code VARCHAR(255) DEFAULT NULL, CHANGE total_cost total_cost NUMERIC(10, 2) DEFAULT NULL, CHANGE status status VARCHAR(255) DEFAULT NULL, CHANGE penalty_days_applied penalty_days_applied INT NOT NULL, CHANGE bonus_applied bonus_applied TINYINT NOT NULL, CHANGE user_id user_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE resource_assignment ADD CONSTRAINT FK_50DD033CA76ED395 FOREIGN KEY (user_id) REFERENCES utilisateurs (id)');
        $this->addSql('ALTER TABLE resource_assignment RENAME INDEX fk_assignment_user TO IDX_50DD033CA76ED395');
        $this->addSql('DROP INDEX resource_code ON resources');
        $this->addSql('ALTER TABLE resources CHANGE resource_code resource_code VARCHAR(255) NOT NULL, CHANGE resource_name resource_name VARCHAR(255) NOT NULL, CHANGE resource_type resource_type VARCHAR(255) NOT NULL, CHANGE unit_cost unit_cost NUMERIC(10, 2) NOT NULL, CHANGE status status VARCHAR(255) DEFAULT NULL');
        $this->addSql('DROP INDEX formation_id ON resultat');
        $this->addSql('ALTER TABLE resultat CHANGE date_passage date_passage DATETIME DEFAULT NULL');
        $this->addSql('DROP INDEX fk_tasks_created_by ON tasks');
        $this->addSql('DROP INDEX idx_tasks_assigned_to ON tasks');
        $this->addSql('DROP INDEX idx_tasks_due_date ON tasks');
        $this->addSql('DROP INDEX idx_tasks_priority ON tasks');
        $this->addSql('DROP INDEX idx_tasks_project ON tasks');
        $this->addSql('DROP INDEX idx_tasks_status ON tasks');
        $this->addSql('ALTER TABLE tasks CHANGE id id INT AUTO_INCREMENT NOT NULL, CHANGE description description LONGTEXT DEFAULT NULL, CHANGE status status VARCHAR(255) DEFAULT NULL, CHANGE priority priority VARCHAR(255) DEFAULT NULL, CHANGE created_at created_at DATETIME DEFAULT NULL, CHANGE updated_at updated_at DATETIME DEFAULT NULL, CHANGE project_id project_id INT NOT NULL');
        $this->addSql('ALTER TABLE transaction DROP FOREIGN KEY `fk_transaction_project_budget`');
        $this->addSql('DROP INDEX reference ON transaction');
        $this->addSql('ALTER TABLE transaction CHANGE id id INT AUTO_INCREMENT NOT NULL, CHANGE reference reference VARCHAR(255) DEFAULT NULL, CHANGE cost cost NUMERIC(10, 2) NOT NULL, CHANGE expense_category expense_category VARCHAR(255) DEFAULT NULL, CHANGE project_budget_id project_budget_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE transaction ADD CONSTRAINT FK_723705D12797E36F FOREIGN KEY (project_budget_id) REFERENCES project_budget (id)');
        $this->addSql('ALTER TABLE transaction RENAME INDEX fk_transaction_project_budget TO IDX_723705D12797E36F');
        $this->addSql('DROP INDEX email ON utilisateurs');
        $this->addSql('ALTER TABLE utilisateurs CHANGE nom nom VARCHAR(255) NOT NULL, CHANGE prenom prenom VARCHAR(255) NOT NULL, CHANGE email email VARCHAR(255) NOT NULL, CHANGE telephone telephone VARCHAR(255) DEFAULT NULL, CHANGE role role VARCHAR(255) NOT NULL, CHANGE departement departement VARCHAR(255) DEFAULT NULL, CHANGE statut statut VARCHAR(255) DEFAULT NULL, CHANGE score score INT NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE conversation_participants CHANGE conversation_id conversation_id BIGINT UNSIGNED NOT NULL, CHANGE role role ENUM(\'MEMBER\', \'ADMIN\') DEFAULT \'MEMBER\' NOT NULL, CHANGE nickname nickname VARCHAR(60) DEFAULT NULL, CHANGE joined_at joined_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CHANGE last_read_message_id last_read_message_id BIGINT UNSIGNED DEFAULT NULL');
        $this->addSql('ALTER TABLE formation CHANGE titre titre VARCHAR(100) DEFAULT NULL, CHANGE description description TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE message_attachments CHANGE id id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL, CHANGE message_id message_id BIGINT UNSIGNED NOT NULL, CHANGE mime_type mime_type VARCHAR(120) NOT NULL, CHANGE size_bytes size_bytes BIGINT UNSIGNED NOT NULL, CHANGE created_at created_at DATETIME DEFAULT CURRENT_TIMESTAMP');
        $this->addSql('CREATE INDEX index_attachment_message ON message_attachments (message_id)');
        $this->addSql('ALTER TABLE messages CHANGE id id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL, CHANGE conversation_id conversation_id BIGINT UNSIGNED NOT NULL, CHANGE body body TEXT NOT NULL, CHANGE kind kind VARCHAR(20) DEFAULT \'TEXT\' NOT NULL, CHANGE created_at created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL');
        $this->addSql('CREATE FULLTEXT INDEX ft_messages_body ON messages (body)');
        $this->addSql('CREATE INDEX idx_messages_conv_id ON messages (conversation_id, id)');
        $this->addSql('CREATE INDEX idx_messages_conv_time ON messages (conversation_id, created_at)');
        $this->addSql('CREATE INDEX idx_messages_sender_time ON messages (sender_id, created_at)');
        $this->addSql('ALTER TABLE participer CHANGE date_inscription date_inscription DATETIME DEFAULT CURRENT_TIMESTAMP, CHANGE progression progression INT DEFAULT 0, CHANGE statut statut VARCHAR(20) DEFAULT \'EN_COURS\'');
        $this->addSql('CREATE UNIQUE INDEX user_id ON participer (user_id, formation_id)');
        $this->addSql('ALTER TABLE project_budget CHANGE id id BIGINT AUTO_INCREMENT NOT NULL, CHANGE total_budget total_budget NUMERIC(15, 2) NOT NULL, CHANGE actualSpend actualSpend NUMERIC(15, 2) NOT NULL, CHANGE status status VARCHAR(50) DEFAULT \'PENDING\', CHANGE projectId projectId BIGINT UNSIGNED NOT NULL');
        $this->addSql('CREATE INDEX fk_pro_id ON project_budget (projectId)');
        $this->addSql('ALTER TABLE projects CHANGE id id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL, CHANGE description description TEXT DEFAULT NULL, CHANGE budget budget NUMERIC(12, 2) DEFAULT NULL, CHANGE progress progress INT DEFAULT 0, CHANGE created_at created_at DATETIME DEFAULT CURRENT_TIMESTAMP, CHANGE updated_at updated_at DATETIME DEFAULT CURRENT_TIMESTAMP');
        $this->addSql('CREATE INDEX idx_projects_assigned_to ON projects (assigned_to)');
        $this->addSql('CREATE INDEX idx_projects_created_by ON projects (created_by)');
        $this->addSql('CREATE INDEX idx_projects_end_date ON projects (end_date)');
        $this->addSql('CREATE INDEX idx_projects_start_date ON projects (start_date)');
        $this->addSql('ALTER TABLE quiz CHANGE question question TEXT DEFAULT NULL, CHANGE correct correct INT DEFAULT 1 NOT NULL');
        $this->addSql('CREATE INDEX formation_id ON quiz (formation_id)');
        $this->addSql('ALTER TABLE reclamation MODIFY id_rec INT NOT NULL');
        $this->addSql('ALTER TABLE reclamation CHANGE categorie categorie VARCHAR(100) DEFAULT NULL, CHANGE statut statut VARCHAR(50) DEFAULT NULL, CHANGE date date VARCHAR(50) DEFAULT NULL, CHANGE id_rec idRec INT AUTO_INCREMENT NOT NULL, DROP PRIMARY KEY, ADD PRIMARY KEY (idRec)');
        $this->addSql('CREATE INDEX fk_reclamation_user ON reclamation (id_user)');
        $this->addSql('ALTER TABLE resource_assignment DROP FOREIGN KEY FK_50DD033CA76ED395');
        $this->addSql('ALTER TABLE resource_assignment CHANGE project_code project_code VARCHAR(50) NOT NULL, CHANGE client_code client_code VARCHAR(50) DEFAULT NULL, CHANGE total_cost total_cost NUMERIC(12, 2) DEFAULT NULL, CHANGE status status VARCHAR(50) DEFAULT \'ACTIVE\', CHANGE penalty_days_applied penalty_days_applied INT DEFAULT 0 NOT NULL, CHANGE bonus_applied bonus_applied TINYINT DEFAULT 0 NOT NULL, CHANGE user_id user_id INT NOT NULL');
        $this->addSql('ALTER TABLE resource_assignment ADD CONSTRAINT `fk_assignment_user` FOREIGN KEY (user_id) REFERENCES utilisateurs (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('CREATE INDEX fk_ra_resource ON resource_assignment (resource_id)');
        $this->addSql('ALTER TABLE resource_assignment RENAME INDEX idx_50dd033ca76ed395 TO fk_assignment_user');
        $this->addSql('ALTER TABLE resources CHANGE resource_code resource_code VARCHAR(50) NOT NULL, CHANGE resource_name resource_name VARCHAR(150) NOT NULL, CHANGE resource_type resource_type ENUM(\'PHYSICAL\', \'SOFTWARE\') NOT NULL, CHANGE unit_cost unit_cost NUMERIC(12, 2) NOT NULL, CHANGE status status VARCHAR(50) DEFAULT \'AVAILABLE\'');
        $this->addSql('CREATE UNIQUE INDEX resource_code ON resources (resource_code)');
        $this->addSql('ALTER TABLE resultat CHANGE date_passage date_passage DATETIME DEFAULT CURRENT_TIMESTAMP');
        $this->addSql('CREATE INDEX formation_id ON resultat (formation_id)');
        $this->addSql('ALTER TABLE tasks CHANGE id id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL, CHANGE description description TEXT DEFAULT NULL, CHANGE status status VARCHAR(50) DEFAULT NULL, CHANGE priority priority ENUM(\'LOW\', \'MEDIUM\', \'HIGH\') DEFAULT \'MEDIUM\', CHANGE created_at created_at DATETIME DEFAULT CURRENT_TIMESTAMP, CHANGE updated_at updated_at DATETIME DEFAULT CURRENT_TIMESTAMP, CHANGE project_id project_id BIGINT UNSIGNED NOT NULL');
        $this->addSql('CREATE INDEX fk_tasks_created_by ON tasks (created_by)');
        $this->addSql('CREATE INDEX idx_tasks_assigned_to ON tasks (assigned_to)');
        $this->addSql('CREATE INDEX idx_tasks_due_date ON tasks (due_date)');
        $this->addSql('CREATE INDEX idx_tasks_priority ON tasks (priority)');
        $this->addSql('CREATE INDEX idx_tasks_project ON tasks (project_id)');
        $this->addSql('CREATE INDEX idx_tasks_status ON tasks (status)');
        $this->addSql('ALTER TABLE transaction DROP FOREIGN KEY FK_723705D12797E36F');
        $this->addSql('ALTER TABLE transaction CHANGE id id BIGINT AUTO_INCREMENT NOT NULL, CHANGE reference reference VARCHAR(50) DEFAULT NULL, CHANGE cost cost NUMERIC(15, 2) NOT NULL, CHANGE expense_category expense_category VARCHAR(100) DEFAULT NULL, CHANGE project_budget_id project_budget_id BIGINT NOT NULL');
        $this->addSql('ALTER TABLE transaction ADD CONSTRAINT `fk_transaction_project_budget` FOREIGN KEY (project_budget_id) REFERENCES project_budget (id) ON UPDATE CASCADE ON DELETE CASCADE');
        $this->addSql('CREATE UNIQUE INDEX reference ON transaction (reference)');
        $this->addSql('ALTER TABLE transaction RENAME INDEX idx_723705d12797e36f TO fk_transaction_project_budget');
        $this->addSql('ALTER TABLE utilisateurs CHANGE nom nom VARCHAR(50) NOT NULL, CHANGE prenom prenom VARCHAR(50) NOT NULL, CHANGE email email VARCHAR(150) NOT NULL, CHANGE telephone telephone VARCHAR(20) DEFAULT NULL, CHANGE role role VARCHAR(50) NOT NULL, CHANGE departement departement VARCHAR(100) DEFAULT NULL, CHANGE statut statut VARCHAR(20) DEFAULT \'Pending\', CHANGE score score INT DEFAULT 100 NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX email ON utilisateurs (email)');
    }
}

