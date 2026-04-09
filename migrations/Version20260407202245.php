<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260407202245 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE INDEX fiscal_year ON budget_profile (fiscal_year)');
        $this->addSql('CREATE INDEX idx_cp_conversation ON conversation_participants (conversation_id)');
        $this->addSql('CREATE INDEX idx_cp_last_read ON conversation_participants (last_read_message_id)');
        $this->addSql('CREATE INDEX idx_cp_nickname ON conversation_participants (nickname)');
        $this->addSql('CREATE INDEX idx_cp_user_active ON conversation_participants (user_id)');
        $this->addSql('CREATE INDEX idx_conversations_created_by ON conversations (created_by)');
        $this->addSql('CREATE INDEX idx_conversations_last ON conversations (last_message_id)');
        $this->addSql('CREATE UNIQUE INDEX uq_conversations_dm_key ON conversations (dm_key)');
        $this->addSql('CREATE INDEX index_attachment_message ON message_attachments (message_id)');
        $this->addSql('CREATE INDEX idx_messages_conv_id ON messages (conversation_id)');
        $this->addSql('CREATE INDEX idx_messages_conv_time ON messages (conversation_id, created_at)');
        $this->addSql('CREATE INDEX idx_messages_sender_time ON messages (sender_id, created_at)');
        $this->addSql('CREATE INDEX user_id ON participer (user_id)');
        $this->addSql('ALTER TABLE project_budget CHANGE id id INT AUTO_INCREMENT NOT NULL, CHANGE total_budget total_budget NUMERIC(10, 2) NOT NULL, CHANGE actualSpend actualSpend NUMERIC(10, 2) NOT NULL, CHANGE status status VARCHAR(255) DEFAULT NULL, CHANGE projectId projectId INT NOT NULL');
        $this->addSql('ALTER TABLE project_budget ADD CONSTRAINT FK_64561C316C9360F7 FOREIGN KEY (projectId) REFERENCES projects (id)');
        $this->addSql('CREATE INDEX fk_pro_id ON project_budget (projectId)');
        $this->addSql('ALTER TABLE projects CHANGE id id INT AUTO_INCREMENT NOT NULL, CHANGE description description LONGTEXT DEFAULT NULL, CHANGE budget budget NUMERIC(10, 2) DEFAULT NULL, CHANGE progress progress INT DEFAULT NULL, CHANGE created_at created_at DATETIME DEFAULT NULL, CHANGE updated_at updated_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE quiz CHANGE question question LONGTEXT DEFAULT NULL, CHANGE correct correct INT NOT NULL');
        $this->addSql('ALTER TABLE reclamation MODIFY idRec INT NOT NULL');
        $this->addSql('ALTER TABLE reclamation CHANGE categorie categorie VARCHAR(255) DEFAULT NULL, CHANGE statut statut VARCHAR(255) DEFAULT NULL, CHANGE date date VARCHAR(255) DEFAULT NULL, CHANGE idRec id_rec INT AUTO_INCREMENT NOT NULL, DROP PRIMARY KEY, ADD PRIMARY KEY (id_rec)');        $this->addSql('ALTER TABLE resource_assignment CHANGE project_code project_code VARCHAR(255) NOT NULL, CHANGE client_code client_code VARCHAR(255) DEFAULT NULL, CHANGE total_cost total_cost NUMERIC(10, 2) DEFAULT NULL, CHANGE status status VARCHAR(255) DEFAULT NULL, CHANGE penalty_days_applied penalty_days_applied INT NOT NULL, CHANGE bonus_applied bonus_applied TINYINT NOT NULL, CHANGE user_id user_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE resource_assignment ADD CONSTRAINT FK_50DD033CA76ED395 FOREIGN KEY (user_id) REFERENCES utilisateurs (id)');        $this->addSql('ALTER TABLE resources CHANGE resource_code resource_code VARCHAR(255) NOT NULL, CHANGE resource_name resource_name VARCHAR(255) NOT NULL, CHANGE resource_type resource_type VARCHAR(255) NOT NULL, CHANGE unit_cost unit_cost NUMERIC(10, 2) NOT NULL, CHANGE status status VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE resultat CHANGE date_passage date_passage DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE tasks CHANGE id id INT AUTO_INCREMENT NOT NULL, CHANGE description description LONGTEXT DEFAULT NULL, CHANGE status status VARCHAR(255) DEFAULT NULL, CHANGE priority priority VARCHAR(255) DEFAULT NULL, CHANGE created_at created_at DATETIME DEFAULT NULL, CHANGE updated_at updated_at DATETIME DEFAULT NULL, CHANGE project_id project_id INT NOT NULL');
        $this->addSql('ALTER TABLE transaction DROP INDEX reference, ADD INDEX reference (reference)');        $this->addSql('ALTER TABLE transaction CHANGE id id INT AUTO_INCREMENT NOT NULL, CHANGE reference reference VARCHAR(255) DEFAULT NULL, CHANGE cost cost NUMERIC(10, 2) NOT NULL, CHANGE expense_category expense_category VARCHAR(255) DEFAULT NULL, CHANGE project_budget_id project_budget_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE transaction ADD CONSTRAINT FK_723705D12797E36F FOREIGN KEY (project_budget_id) REFERENCES project_budget (id)');        $this->addSql('ALTER TABLE utilisateurs CHANGE nom nom VARCHAR(255) NOT NULL, CHANGE prenom prenom VARCHAR(255) NOT NULL, CHANGE email email VARCHAR(255) NOT NULL, CHANGE telephone telephone VARCHAR(255) DEFAULT NULL, CHANGE role role VARCHAR(255) NOT NULL, CHANGE departement departement VARCHAR(255) DEFAULT NULL, CHANGE statut statut VARCHAR(255) DEFAULT NULL, CHANGE score score INT NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs        $this->addSql('DROP INDEX idx_cp_user_active ON conversation_participants');        $this->addSql('DROP INDEX index_attachment_message ON message_attachments');        $this->addSql('ALTER TABLE project_budget DROP FOREIGN KEY FK_64561C316C9360F7');        $this->addSql('ALTER TABLE project_budget CHANGE id id BIGINT AUTO_INCREMENT NOT NULL, CHANGE total_budget total_budget NUMERIC(15, 2) NOT NULL, CHANGE actualSpend actualSpend NUMERIC(15, 2) NOT NULL, CHANGE status status VARCHAR(50) DEFAULT \'PENDING\', CHANGE projectId projectId BIGINT UNSIGNED NOT NULL');
        $this->addSql('ALTER TABLE projects CHANGE id id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL, CHANGE description description TEXT DEFAULT NULL, CHANGE budget budget NUMERIC(12, 2) DEFAULT NULL, CHANGE progress progress INT DEFAULT 0, CHANGE created_at created_at DATETIME DEFAULT CURRENT_TIMESTAMP, CHANGE updated_at updated_at DATETIME DEFAULT CURRENT_TIMESTAMP');
        $this->addSql('ALTER TABLE quiz CHANGE question question TEXT DEFAULT NULL, CHANGE correct correct INT DEFAULT 1 NOT NULL');
        $this->addSql('ALTER TABLE reclamation MODIFY id_rec INT NOT NULL');
        $this->addSql('ALTER TABLE reclamation CHANGE categorie categorie VARCHAR(100) DEFAULT NULL, CHANGE statut statut VARCHAR(50) DEFAULT NULL, CHANGE date date VARCHAR(50) DEFAULT NULL, CHANGE id_rec idRec INT AUTO_INCREMENT NOT NULL, DROP PRIMARY KEY, ADD PRIMARY KEY (idRec)');        $this->addSql('ALTER TABLE resource_assignment CHANGE project_code project_code VARCHAR(50) NOT NULL, CHANGE client_code client_code VARCHAR(50) DEFAULT NULL, CHANGE total_cost total_cost NUMERIC(12, 2) DEFAULT NULL, CHANGE status status VARCHAR(50) DEFAULT \'ACTIVE\', CHANGE penalty_days_applied penalty_days_applied INT DEFAULT 0 NOT NULL, CHANGE bonus_applied bonus_applied TINYINT DEFAULT 0 NOT NULL, CHANGE user_id user_id INT NOT NULL');
        $this->addSql('ALTER TABLE resource_assignment ADD CONSTRAINT `fk_assignment_user` FOREIGN KEY (user_id) REFERENCES utilisateurs (id) ON UPDATE NO ACTION ON DELETE CASCADE');        $this->addSql('ALTER TABLE resources CHANGE resource_code resource_code VARCHAR(50) NOT NULL, CHANGE resource_name resource_name VARCHAR(150) NOT NULL, CHANGE resource_type resource_type ENUM(\'PHYSICAL\', \'SOFTWARE\') NOT NULL, CHANGE unit_cost unit_cost NUMERIC(12, 2) NOT NULL, CHANGE status status VARCHAR(50) DEFAULT \'AVAILABLE\'');
        $this->addSql('ALTER TABLE resultat CHANGE date_passage date_passage DATETIME DEFAULT CURRENT_TIMESTAMP');
        $this->addSql('ALTER TABLE tasks CHANGE id id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL, CHANGE description description TEXT DEFAULT NULL, CHANGE status status VARCHAR(50) DEFAULT NULL, CHANGE priority priority ENUM(\'LOW\', \'MEDIUM\', \'HIGH\') DEFAULT \'MEDIUM\', CHANGE created_at created_at DATETIME DEFAULT CURRENT_TIMESTAMP, CHANGE updated_at updated_at DATETIME DEFAULT CURRENT_TIMESTAMP, CHANGE project_id project_id BIGINT UNSIGNED NOT NULL');
        $this->addSql('ALTER TABLE transaction DROP INDEX reference, ADD UNIQUE INDEX reference (reference)');        $this->addSql('ALTER TABLE transaction CHANGE id id BIGINT AUTO_INCREMENT NOT NULL, CHANGE reference reference VARCHAR(50) DEFAULT NULL, CHANGE cost cost NUMERIC(15, 2) NOT NULL, CHANGE expense_category expense_category VARCHAR(100) DEFAULT NULL, CHANGE project_budget_id project_budget_id BIGINT NOT NULL');
        $this->addSql('ALTER TABLE transaction ADD CONSTRAINT `fk_transaction_project_budget` FOREIGN KEY (project_budget_id) REFERENCES project_budget (id) ON UPDATE CASCADE ON DELETE CASCADE');        $this->addSql('ALTER TABLE utilisateurs CHANGE nom nom VARCHAR(50) NOT NULL, CHANGE prenom prenom VARCHAR(50) NOT NULL, CHANGE email email VARCHAR(150) NOT NULL, CHANGE telephone telephone VARCHAR(20) DEFAULT NULL, CHANGE role role VARCHAR(50) NOT NULL, CHANGE departement departement VARCHAR(100) DEFAULT NULL, CHANGE statut statut VARCHAR(20) DEFAULT \'Pending\', CHANGE score score INT DEFAULT 100 NOT NULL');
    }
}
