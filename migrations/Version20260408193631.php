<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260408193631 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE IF NOT EXISTS participer (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, formation_id INT NOT NULL, date_inscription DATETIME DEFAULT NULL, progression INT DEFAULT NULL, statut VARCHAR(255) DEFAULT NULL, INDEX user_id (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE IF NOT EXISTS resultat (id INT AUTO_INCREMENT NOT NULL, formation_id INT NOT NULL, score INT NOT NULL, total INT NOT NULL, date_passage DATETIME DEFAULT NULL, INDEX formation_id (formation_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE IF NOT EXISTS messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE budget_profile DROP INDEX fiscal_year, ADD INDEX fiscal_year (fiscal_year)');
        $this->addSql('ALTER TABLE budget_profile CHANGE id id INT AUTO_INCREMENT NOT NULL, CHANGE fiscal_year fiscal_year VARCHAR(255) NOT NULL, CHANGE budget_disposable budget_disposable NUMERIC(10, 2) NOT NULL, CHANGE total_expense total_expense NUMERIC(10, 2) DEFAULT NULL, CHANGE margin_profit margin_profit DOUBLE PRECISION DEFAULT NULL');
        // Skipped: DROP INDEX idx_cp_user_active ON conversation_participants (does not exist)
        // Skipped: DROP INDEX idx_cp_conversation ON conversation_participants (does not exist)
        // Skipped: DROP INDEX idx_cp_nickname ON conversation_participants (does not exist)
        $this->addSql('ALTER TABLE conversation_participants CHANGE conversation_id conversation_id INT NOT NULL, CHANGE role role VARCHAR(255) NOT NULL, CHANGE nickname nickname VARCHAR(255) DEFAULT NULL, CHANGE joined_at joined_at DATETIME NOT NULL, CHANGE last_read_message_id last_read_message_id INT DEFAULT NULL');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_cp_user_active ON conversation_participants (user_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_cp_conversation ON conversation_participants (conversation_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_cp_nickname ON conversation_participants (nickname)');
        // Skipped: DROP INDEX idx_conversations_last ON conversations (does not exist)
        $this->addSql('ALTER TABLE conversations CHANGE id id INT AUTO_INCREMENT NOT NULL, CHANGE type type VARCHAR(255) NOT NULL, CHANGE avatar_mime avatar_mime VARCHAR(255) DEFAULT NULL, CHANGE dm_key dm_key VARCHAR(255) DEFAULT NULL, CHANGE last_message_id last_message_id INT DEFAULT NULL, CHANGE created_at created_at DATETIME NOT NULL');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_conversations_last ON conversations (last_message_id)');
        $this->addSql('ALTER TABLE formation CHANGE titre titre VARCHAR(255) DEFAULT NULL, CHANGE description description LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE message_attachments CHANGE id id INT AUTO_INCREMENT NOT NULL, CHANGE message_id message_id INT NOT NULL, CHANGE mime_type mime_type VARCHAR(255) NOT NULL, CHANGE size_bytes size_bytes INT NOT NULL, CHANGE created_at created_at DATETIME DEFAULT NULL');
        // Skipped: DROP INDEX idx_messages_conv_id ON messages (does not exist)
        // Skipped: DROP INDEX ft_messages_body ON messages (does not exist)
        $this->addSql('ALTER TABLE messages CHANGE id id INT AUTO_INCREMENT NOT NULL, CHANGE conversation_id conversation_id INT NOT NULL, CHANGE body body LONGTEXT NOT NULL, CHANGE kind kind VARCHAR(255) NOT NULL, CHANGE created_at created_at DATETIME NOT NULL');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_messages_conv_id ON messages (conversation_id)');
        $this->addSql('ALTER TABLE project_budget CHANGE id id INT AUTO_INCREMENT NOT NULL, CHANGE total_budget total_budget NUMERIC(10, 2) NOT NULL, CHANGE actualSpend actualSpend NUMERIC(10, 2) NOT NULL, CHANGE status status VARCHAR(255) DEFAULT NULL, CHANGE projectId projectId INT NOT NULL');
        $this->addSql('ALTER TABLE projects CHANGE id id INT AUTO_INCREMENT NOT NULL, CHANGE description description LONGTEXT DEFAULT NULL, CHANGE budget budget NUMERIC(10, 2) DEFAULT NULL, CHANGE progress progress INT DEFAULT NULL, CHANGE created_at created_at DATETIME DEFAULT NULL, CHANGE updated_at updated_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE quiz CHANGE question question LONGTEXT DEFAULT NULL, CHANGE correct correct INT NOT NULL');
        // Skipped: ALTER TABLE reclamation (table does not exist)
        // Skipped: ALTER TABLE resource_assignment DROP FOREIGN KEY fk_assignment_user (already renamed to FK_50DD033CA76ED395)
        $this->addSql('ALTER TABLE resource_assignment CHANGE project_code project_code VARCHAR(255) NOT NULL, CHANGE client_code client_code VARCHAR(255) DEFAULT NULL, CHANGE total_cost total_cost NUMERIC(10, 2) DEFAULT NULL, CHANGE status status VARCHAR(255) DEFAULT NULL, CHANGE penalty_days_applied penalty_days_applied INT NOT NULL, CHANGE bonus_applied bonus_applied TINYINT NOT NULL, CHANGE user_id user_id INT DEFAULT NULL');
        // Skipped: ADD CONSTRAINT FK_50DD033CA76ED395 (already exists)
        // Skipped: RENAME INDEX fk_assignment_user TO IDX_50DD033CA76ED395 (already renamed)
        $this->addSql('ALTER TABLE resources CHANGE resource_code resource_code VARCHAR(255) NOT NULL, CHANGE resource_name resource_name VARCHAR(255) NOT NULL, CHANGE resource_type resource_type VARCHAR(255) NOT NULL, CHANGE unit_cost unit_cost NUMERIC(10, 2) NOT NULL, CHANGE status status VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE tasks CHANGE id id INT AUTO_INCREMENT NOT NULL, CHANGE description description LONGTEXT DEFAULT NULL, CHANGE status status VARCHAR(255) DEFAULT NULL, CHANGE priority priority VARCHAR(255) DEFAULT NULL, CHANGE created_at created_at DATETIME DEFAULT NULL, CHANGE updated_at updated_at DATETIME DEFAULT NULL, CHANGE project_id project_id INT NOT NULL');
        $this->addSql('ALTER TABLE transaction DROP INDEX reference, ADD INDEX reference (reference)');
        // Skipped: DROP FOREIGN KEY fk_transaction_project_budget (already renamed to FK_723705D12797E36F)
        $this->addSql('ALTER TABLE transaction CHANGE id id INT AUTO_INCREMENT NOT NULL, CHANGE reference reference VARCHAR(255) DEFAULT NULL, CHANGE cost cost NUMERIC(10, 2) NOT NULL, CHANGE expense_category expense_category VARCHAR(255) DEFAULT NULL, CHANGE project_budget_id project_budget_id INT DEFAULT NULL');
        // Skipped: ADD CONSTRAINT FK_723705D12797E36F (already exists)
        // Skipped: RENAME INDEX fk_transaction_project_budget TO IDX_723705D12797E36F (already renamed)
        $this->addSql('ALTER TABLE utilisateurs CHANGE nom nom VARCHAR(255) NOT NULL, CHANGE prenom prenom VARCHAR(255) NOT NULL, CHANGE email email VARCHAR(255) NOT NULL, CHANGE telephone telephone VARCHAR(255) DEFAULT NULL, CHANGE role role VARCHAR(255) NOT NULL, CHANGE departement departement VARCHAR(255) DEFAULT NULL, CHANGE statut statut VARCHAR(255) DEFAULT NULL, CHANGE score score INT NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE participer');
        $this->addSql('DROP TABLE resultat');
        $this->addSql('DROP TABLE messenger_messages');
        $this->addSql('ALTER TABLE budget_profile DROP INDEX fiscal_year, ADD UNIQUE INDEX fiscal_year (fiscal_year)');
        $this->addSql('ALTER TABLE budget_profile CHANGE id id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL, CHANGE fiscal_year fiscal_year DATE NOT NULL, CHANGE budget_disposable budget_disposable NUMERIC(15, 2) NOT NULL, CHANGE total_expense total_expense NUMERIC(15, 2) DEFAULT \'0.00\', CHANGE margin_profit margin_profit FLOAT DEFAULT \'0\'');
        $this->addSql('DROP INDEX idx_cp_conversation ON conversation_participants');
        $this->addSql('DROP INDEX idx_cp_nickname ON conversation_participants');
        $this->addSql('DROP INDEX idx_cp_user_active ON conversation_participants');
        $this->addSql('ALTER TABLE conversation_participants CHANGE conversation_id conversation_id BIGINT UNSIGNED NOT NULL, CHANGE role role ENUM(\'MEMBER\', \'ADMIN\') DEFAULT \'MEMBER\' NOT NULL, CHANGE nickname nickname VARCHAR(60) DEFAULT NULL, CHANGE joined_at joined_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CHANGE last_read_message_id last_read_message_id BIGINT UNSIGNED DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_cp_conversation ON conversation_participants (conversation_id, user_id)');
        $this->addSql('CREATE INDEX idx_cp_nickname ON conversation_participants (conversation_id, user_id, nickname)');
        $this->addSql('CREATE INDEX idx_cp_user_active ON conversation_participants (user_id, left_at, conversation_id)');
        $this->addSql('DROP INDEX idx_conversations_last ON conversations');
        $this->addSql('ALTER TABLE conversations CHANGE id id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL, CHANGE type type ENUM(\'DM\', \'GROUP\') NOT NULL, CHANGE avatar_mime avatar_mime VARCHAR(50) DEFAULT NULL, CHANGE dm_key dm_key VARCHAR(25) DEFAULT NULL, CHANGE last_message_id last_message_id BIGINT UNSIGNED DEFAULT NULL, CHANGE created_at created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL');
        $this->addSql('CREATE INDEX idx_conversations_last ON conversations (last_message_at, id)');
        $this->addSql('ALTER TABLE formation CHANGE titre titre VARCHAR(100) DEFAULT NULL, CHANGE description description TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE message_attachments CHANGE id id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL, CHANGE message_id message_id BIGINT UNSIGNED NOT NULL, CHANGE mime_type mime_type VARCHAR(120) NOT NULL, CHANGE size_bytes size_bytes BIGINT UNSIGNED NOT NULL, CHANGE created_at created_at DATETIME DEFAULT CURRENT_TIMESTAMP');
        $this->addSql('DROP INDEX idx_messages_conv_id ON messages');
        $this->addSql('ALTER TABLE messages CHANGE id id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL, CHANGE conversation_id conversation_id BIGINT UNSIGNED NOT NULL, CHANGE body body TEXT NOT NULL, CHANGE kind kind VARCHAR(20) DEFAULT \'TEXT\' NOT NULL, CHANGE created_at created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL');
        $this->addSql('CREATE FULLTEXT INDEX ft_messages_body ON messages (body)');
        $this->addSql('CREATE INDEX idx_messages_conv_id ON messages (conversation_id, id)');
        $this->addSql('ALTER TABLE project_budget CHANGE id id BIGINT AUTO_INCREMENT NOT NULL, CHANGE total_budget total_budget NUMERIC(15, 2) NOT NULL, CHANGE actualSpend actualSpend NUMERIC(15, 2) NOT NULL, CHANGE status status VARCHAR(50) DEFAULT \'PENDING\', CHANGE projectId projectId BIGINT UNSIGNED NOT NULL');
        $this->addSql('ALTER TABLE projects CHANGE id id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL, CHANGE description description TEXT DEFAULT NULL, CHANGE budget budget NUMERIC(12, 2) DEFAULT NULL, CHANGE progress progress INT DEFAULT 0, CHANGE created_at created_at DATETIME DEFAULT CURRENT_TIMESTAMP, CHANGE updated_at updated_at DATETIME DEFAULT CURRENT_TIMESTAMP');
        $this->addSql('ALTER TABLE quiz CHANGE question question TEXT DEFAULT NULL, CHANGE correct correct INT DEFAULT 1 NOT NULL');
        $this->addSql('ALTER TABLE reclamation MODIFY id_rec INT NOT NULL');
        $this->addSql('ALTER TABLE reclamation CHANGE categorie categorie VARCHAR(100) DEFAULT NULL, CHANGE statut statut VARCHAR(50) DEFAULT NULL, CHANGE date date VARCHAR(50) DEFAULT NULL, CHANGE id_rec idRec INT AUTO_INCREMENT NOT NULL, DROP PRIMARY KEY, ADD PRIMARY KEY (idRec)');
        $this->addSql('ALTER TABLE resource_assignment DROP FOREIGN KEY FK_50DD033CA76ED395');
        $this->addSql('ALTER TABLE resource_assignment CHANGE project_code project_code VARCHAR(50) NOT NULL, CHANGE client_code client_code VARCHAR(50) DEFAULT NULL, CHANGE total_cost total_cost NUMERIC(12, 2) DEFAULT NULL, CHANGE status status VARCHAR(50) DEFAULT \'ACTIVE\', CHANGE penalty_days_applied penalty_days_applied INT DEFAULT 0 NOT NULL, CHANGE bonus_applied bonus_applied TINYINT DEFAULT 0 NOT NULL, CHANGE user_id user_id INT NOT NULL');
        $this->addSql('ALTER TABLE resource_assignment ADD CONSTRAINT `fk_assignment_user` FOREIGN KEY (user_id) REFERENCES utilisateurs (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE resource_assignment RENAME INDEX idx_50dd033ca76ed395 TO fk_assignment_user');
        $this->addSql('ALTER TABLE resources CHANGE resource_code resource_code VARCHAR(50) NOT NULL, CHANGE resource_name resource_name VARCHAR(150) NOT NULL, CHANGE resource_type resource_type ENUM(\'PHYSICAL\', \'SOFTWARE\') NOT NULL, CHANGE unit_cost unit_cost NUMERIC(12, 2) NOT NULL, CHANGE status status VARCHAR(50) DEFAULT \'AVAILABLE\'');
        $this->addSql('ALTER TABLE tasks CHANGE id id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL, CHANGE description description TEXT DEFAULT NULL, CHANGE status status VARCHAR(50) DEFAULT NULL, CHANGE priority priority ENUM(\'LOW\', \'MEDIUM\', \'HIGH\') DEFAULT \'MEDIUM\', CHANGE created_at created_at DATETIME DEFAULT CURRENT_TIMESTAMP, CHANGE updated_at updated_at DATETIME DEFAULT CURRENT_TIMESTAMP, CHANGE project_id project_id BIGINT UNSIGNED NOT NULL');
        $this->addSql('ALTER TABLE transaction DROP INDEX reference, ADD UNIQUE INDEX reference (reference)');
        $this->addSql('ALTER TABLE transaction DROP FOREIGN KEY FK_723705D12797E36F');
        $this->addSql('ALTER TABLE transaction CHANGE id id BIGINT AUTO_INCREMENT NOT NULL, CHANGE reference reference VARCHAR(50) DEFAULT NULL, CHANGE cost cost NUMERIC(15, 2) NOT NULL, CHANGE expense_category expense_category VARCHAR(100) DEFAULT NULL, CHANGE project_budget_id project_budget_id BIGINT NOT NULL');
        $this->addSql('ALTER TABLE transaction ADD CONSTRAINT `fk_transaction_project_budget` FOREIGN KEY (project_budget_id) REFERENCES project_budget (id) ON UPDATE CASCADE ON DELETE CASCADE');
        $this->addSql('ALTER TABLE transaction RENAME INDEX idx_723705d12797e36f TO fk_transaction_project_budget');
        $this->addSql('ALTER TABLE utilisateurs CHANGE nom nom VARCHAR(50) NOT NULL, CHANGE prenom prenom VARCHAR(50) NOT NULL, CHANGE email email VARCHAR(150) NOT NULL, CHANGE telephone telephone VARCHAR(20) DEFAULT NULL, CHANGE role role VARCHAR(50) NOT NULL, CHANGE departement departement VARCHAR(100) DEFAULT NULL, CHANGE statut statut VARCHAR(20) DEFAULT \'Pending\', CHANGE score score INT DEFAULT 100 NOT NULL');
    }
}