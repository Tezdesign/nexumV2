<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260421160851 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE participer (id INT AUTO_INCREMENT NOT NULL, date_inscription DATETIME NOT NULL, progression INT NOT NULL, statut VARCHAR(50) NOT NULL, user_id INT NOT NULL, formation_id INT NOT NULL, INDEX IDX_EDBE16F8A76ED395 (user_id), INDEX IDX_EDBE16F85200282E (formation_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE resultat (id INT AUTO_INCREMENT NOT NULL, score INT NOT NULL, total INT NOT NULL, date_passage DATETIME NOT NULL, formation_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_E7DB5DE25200282E (formation_id), INDEX IDX_E7DB5DE2A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE participer ADD CONSTRAINT FK_EDBE16F8A76ED395 FOREIGN KEY (user_id) REFERENCES utilisateurs (id)');
        $this->addSql('ALTER TABLE participer ADD CONSTRAINT FK_EDBE16F85200282E FOREIGN KEY (formation_id) REFERENCES formation (id)');
        $this->addSql('ALTER TABLE resultat ADD CONSTRAINT FK_E7DB5DE25200282E FOREIGN KEY (formation_id) REFERENCES formation (id)');
        $this->addSql('ALTER TABLE resultat ADD CONSTRAINT FK_E7DB5DE2A76ED395 FOREIGN KEY (user_id) REFERENCES utilisateurs (id)');
        $this->addSql('ALTER TABLE budget_profile DROP INDEX fiscal_year, ADD INDEX fiscal_year (fiscal_year)');
        $this->addSql('ALTER TABLE budget_profile ADD base_currency VARCHAR(3) DEFAULT NULL, ADD start_date DATE DEFAULT NULL, ADD end_date DATE DEFAULT NULL, ADD status VARCHAR(50) DEFAULT \'DRAFT\' NOT NULL, CHANGE id id INT AUTO_INCREMENT NOT NULL, CHANGE fiscal_year fiscal_year VARCHAR(255) NOT NULL, CHANGE budget_disposable budget_disposable NUMERIC(10, 2) NOT NULL, CHANGE total_expense total_expense NUMERIC(10, 2) DEFAULT NULL, CHANGE margin_profit margin_profit DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('DROP INDEX idx_cp_user_active ON conversation_participants');
        $this->addSql('DROP INDEX idx_cp_nickname ON conversation_participants');
        $this->addSql('DROP INDEX idx_cp_conversation ON conversation_participants');
        $this->addSql('CREATE INDEX idx_cp_user_active ON conversation_participants (user_id)');
        $this->addSql('CREATE INDEX idx_cp_nickname ON conversation_participants (nickname)');
        $this->addSql('CREATE INDEX idx_cp_conversation ON conversation_participants (conversation_id)');
        $this->addSql('DROP INDEX idx_conversations_last ON conversations');
        $this->addSql('ALTER TABLE conversations CHANGE id id INT AUTO_INCREMENT NOT NULL, CHANGE type type VARCHAR(255) NOT NULL, CHANGE avatar_mime avatar_mime VARCHAR(255) DEFAULT NULL, CHANGE dm_key dm_key VARCHAR(255) DEFAULT NULL, CHANGE last_message_id last_message_id INT DEFAULT NULL, CHANGE created_at created_at DATETIME NOT NULL');
        $this->addSql('CREATE INDEX idx_conversations_last ON conversations (last_message_id)');
        $this->addSql('ALTER TABLE formation CHANGE titre titre VARCHAR(255) NOT NULL, CHANGE description description LONGTEXT NOT NULL');
        $this->addSql('CREATE INDEX index_attachment_message ON message_attachments (message_id)');
        $this->addSql('CREATE INDEX idx_messages_conv_id ON messages (conversation_id)');
        $this->addSql('CREATE INDEX idx_messages_conv_time ON messages (conversation_id, created_at)');
        $this->addSql('CREATE INDEX idx_messages_sender_time ON messages (sender_id, created_at)');
        $this->addSql('ALTER TABLE project_budget CHANGE id id INT AUTO_INCREMENT NOT NULL, CHANGE total_budget total_budget NUMERIC(10, 2) NOT NULL, CHANGE actualSpend actualSpend NUMERIC(10, 2) NOT NULL, CHANGE status status VARCHAR(255) DEFAULT NULL, CHANGE projectId projectId INT NOT NULL');
        $this->addSql('ALTER TABLE project_budget ADD CONSTRAINT FK_64561C316C9360F7 FOREIGN KEY (projectId) REFERENCES projects (id)');
        $this->addSql('ALTER TABLE projects CHANGE id id INT AUTO_INCREMENT NOT NULL, CHANGE description description LONGTEXT DEFAULT NULL, CHANGE budget budget NUMERIC(10, 2) DEFAULT NULL, CHANGE progress progress INT DEFAULT NULL, CHANGE created_at created_at DATETIME DEFAULT NULL, CHANGE updated_at updated_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE quiz CHANGE question question LONGTEXT NOT NULL, CHANGE r1 r1 VARCHAR(255) NOT NULL, CHANGE r2 r2 VARCHAR(255) NOT NULL, CHANGE r3 r3 VARCHAR(255) NOT NULL, CHANGE formation_id formation_id INT NOT NULL, CHANGE correct correct INT NOT NULL');
        $this->addSql('ALTER TABLE quiz ADD CONSTRAINT FK_A412FA925200282E FOREIGN KEY (formation_id) REFERENCES formation (id)');
        $this->addSql('ALTER TABLE quiz RENAME INDEX formation_id TO IDX_A412FA925200282E');
        $this->addSql('ALTER TABLE reclamation CHANGE categorie categorie VARCHAR(255) DEFAULT NULL, CHANGE statut statut VARCHAR(255) DEFAULT NULL, CHANGE date date VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE resource_assignment DROP FOREIGN KEY `fk_assignment_user`');
        $this->addSql('ALTER TABLE resource_assignment ADD returned TINYINT NOT NULL, CHANGE project_code project_code VARCHAR(255) NOT NULL, CHANGE client_code client_code VARCHAR(255) DEFAULT NULL, CHANGE total_cost total_cost NUMERIC(10, 2) DEFAULT NULL, CHANGE status status VARCHAR(255) DEFAULT NULL, CHANGE penalty_days_applied penalty_days_applied INT NOT NULL, CHANGE bonus_applied bonus_applied TINYINT NOT NULL, CHANGE user_id user_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE resource_assignment ADD CONSTRAINT FK_50DD033CA76ED395 FOREIGN KEY (user_id) REFERENCES utilisateurs (id)');
        $this->addSql('ALTER TABLE resource_assignment RENAME INDEX fk_assignment_user TO IDX_50DD033CA76ED395');
        $this->addSql('DROP INDEX resource_code ON resources');
        $this->addSql('ALTER TABLE resources CHANGE resource_name resource_name VARCHAR(255) NOT NULL, CHANGE resource_type resource_type VARCHAR(50) NOT NULL, CHANGE unit_cost unit_cost NUMERIC(10, 2) NOT NULL, CHANGE status status VARCHAR(50) DEFAULT NULL');
        $this->addSql('ALTER TABLE tasks CHANGE id id INT AUTO_INCREMENT NOT NULL, CHANGE description description LONGTEXT DEFAULT NULL, CHANGE status status VARCHAR(255) DEFAULT NULL, CHANGE priority priority VARCHAR(255) DEFAULT NULL, CHANGE created_at created_at DATETIME DEFAULT NULL, CHANGE updated_at updated_at DATETIME DEFAULT NULL, CHANGE project_id project_id INT NOT NULL');
        $this->addSql('ALTER TABLE transaction DROP INDEX reference, ADD INDEX reference (reference)');
        $this->addSql('ALTER TABLE transaction DROP FOREIGN KEY `fk_transaction_project_budget`');
        $this->addSql('ALTER TABLE transaction CHANGE id id INT AUTO_INCREMENT NOT NULL, CHANGE reference reference VARCHAR(255) DEFAULT NULL, CHANGE cost cost NUMERIC(10, 2) NOT NULL, CHANGE expense_category expense_category VARCHAR(255) DEFAULT NULL, CHANGE project_budget_id project_budget_id INT DEFAULT NULL, CHANGE description description VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE transaction ADD CONSTRAINT FK_723705D12797E36F FOREIGN KEY (project_budget_id) REFERENCES project_budget (id)');
        $this->addSql('ALTER TABLE transaction RENAME INDEX fk_transaction_project_budget TO IDX_723705D12797E36F');
        $this->addSql('ALTER TABLE utilisateurs CHANGE nom nom VARCHAR(255) NOT NULL, CHANGE prenom prenom VARCHAR(255) NOT NULL, CHANGE email email VARCHAR(255) NOT NULL, CHANGE telephone telephone VARCHAR(255) DEFAULT NULL, CHANGE role role VARCHAR(255) NOT NULL, CHANGE departement departement VARCHAR(255) DEFAULT NULL, CHANGE statut statut VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE participer DROP FOREIGN KEY FK_EDBE16F8A76ED395');
        $this->addSql('ALTER TABLE participer DROP FOREIGN KEY FK_EDBE16F85200282E');
        $this->addSql('ALTER TABLE resultat DROP FOREIGN KEY FK_E7DB5DE25200282E');
        $this->addSql('ALTER TABLE resultat DROP FOREIGN KEY FK_E7DB5DE2A76ED395');
        $this->addSql('DROP TABLE participer');
        $this->addSql('DROP TABLE resultat');
        $this->addSql('DROP TABLE messenger_messages');
        $this->addSql('ALTER TABLE budget_profile DROP INDEX fiscal_year, ADD UNIQUE INDEX fiscal_year (fiscal_year)');
        $this->addSql('ALTER TABLE budget_profile DROP base_currency, DROP start_date, DROP end_date, DROP status, CHANGE id id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL, CHANGE fiscal_year fiscal_year DATE NOT NULL, CHANGE budget_disposable budget_disposable NUMERIC(15, 2) NOT NULL, CHANGE total_expense total_expense NUMERIC(15, 2) DEFAULT \'0.00\', CHANGE margin_profit margin_profit FLOAT DEFAULT \'0\'');
        $this->addSql('DROP INDEX idx_cp_conversation ON conversation_participants');
        $this->addSql('DROP INDEX idx_cp_nickname ON conversation_participants');
        $this->addSql('DROP INDEX idx_cp_user_active ON conversation_participants');
        $this->addSql('CREATE INDEX idx_cp_conversation ON conversation_participants (conversation_id, user_id)');
        $this->addSql('CREATE INDEX idx_cp_nickname ON conversation_participants (conversation_id, user_id, nickname)');
        $this->addSql('CREATE INDEX idx_cp_user_active ON conversation_participants (user_id, left_at, conversation_id)');
        $this->addSql('DROP INDEX idx_conversations_last ON conversations');
        $this->addSql('ALTER TABLE conversations CHANGE id id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL, CHANGE type type ENUM(\'DM\', \'GROUP\') NOT NULL, CHANGE avatar_mime avatar_mime VARCHAR(50) DEFAULT NULL, CHANGE dm_key dm_key VARCHAR(25) DEFAULT NULL, CHANGE last_message_id last_message_id BIGINT UNSIGNED DEFAULT NULL, CHANGE created_at created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL');
        $this->addSql('CREATE INDEX idx_conversations_last ON conversations (last_message_at, id)');
        $this->addSql('ALTER TABLE formation CHANGE titre titre VARCHAR(255) DEFAULT NULL, CHANGE description description LONGTEXT DEFAULT NULL');
        $this->addSql('DROP INDEX index_attachment_message ON message_attachments');
        $this->addSql('DROP INDEX idx_messages_conv_id ON messages');
        $this->addSql('DROP INDEX idx_messages_conv_time ON messages');
        $this->addSql('DROP INDEX idx_messages_sender_time ON messages');
        $this->addSql('ALTER TABLE project_budget DROP FOREIGN KEY FK_64561C316C9360F7');
        $this->addSql('ALTER TABLE project_budget CHANGE id id BIGINT AUTO_INCREMENT NOT NULL, CHANGE total_budget total_budget NUMERIC(15, 2) NOT NULL, CHANGE actualSpend actualSpend NUMERIC(15, 2) NOT NULL, CHANGE status status VARCHAR(50) DEFAULT \'PENDING\', CHANGE projectId projectId BIGINT UNSIGNED NOT NULL');
        $this->addSql('ALTER TABLE projects CHANGE id id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL, CHANGE description description TEXT DEFAULT NULL, CHANGE budget budget NUMERIC(12, 2) DEFAULT NULL, CHANGE progress progress INT DEFAULT 0, CHANGE created_at created_at DATETIME DEFAULT CURRENT_TIMESTAMP, CHANGE updated_at updated_at DATETIME DEFAULT CURRENT_TIMESTAMP');
        $this->addSql('ALTER TABLE quiz DROP FOREIGN KEY FK_A412FA925200282E');
        $this->addSql('ALTER TABLE quiz CHANGE question question TEXT DEFAULT NULL, CHANGE r1 r1 VARCHAR(255) DEFAULT NULL, CHANGE r2 r2 VARCHAR(255) DEFAULT NULL, CHANGE r3 r3 VARCHAR(255) DEFAULT NULL, CHANGE correct correct INT DEFAULT 1 NOT NULL, CHANGE formation_id formation_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE quiz RENAME INDEX idx_a412fa925200282e TO formation_id');
        $this->addSql('ALTER TABLE reclamation CHANGE categorie categorie VARCHAR(100) DEFAULT NULL, CHANGE statut statut VARCHAR(50) DEFAULT NULL, CHANGE date date VARCHAR(50) DEFAULT NULL');
        $this->addSql('ALTER TABLE resource_assignment DROP FOREIGN KEY FK_50DD033CA76ED395');
        $this->addSql('ALTER TABLE resource_assignment DROP returned, CHANGE project_code project_code VARCHAR(50) NOT NULL, CHANGE client_code client_code VARCHAR(50) DEFAULT NULL, CHANGE total_cost total_cost NUMERIC(12, 2) DEFAULT NULL, CHANGE status status VARCHAR(50) DEFAULT \'ACTIVE\', CHANGE penalty_days_applied penalty_days_applied INT DEFAULT 0 NOT NULL, CHANGE bonus_applied bonus_applied TINYINT DEFAULT 0 NOT NULL, CHANGE user_id user_id INT NOT NULL');
        $this->addSql('ALTER TABLE resource_assignment ADD CONSTRAINT `fk_assignment_user` FOREIGN KEY (user_id) REFERENCES utilisateurs (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE resource_assignment RENAME INDEX idx_50dd033ca76ed395 TO fk_assignment_user');
        $this->addSql('ALTER TABLE resources CHANGE resource_name resource_name VARCHAR(150) NOT NULL, CHANGE resource_type resource_type ENUM(\'PHYSICAL\', \'SOFTWARE\') NOT NULL, CHANGE unit_cost unit_cost NUMERIC(12, 2) NOT NULL, CHANGE status status VARCHAR(50) DEFAULT \'AVAILABLE\'');
        $this->addSql('CREATE UNIQUE INDEX resource_code ON resources (resource_code)');
        $this->addSql('ALTER TABLE tasks CHANGE id id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL, CHANGE description description TEXT DEFAULT NULL, CHANGE status status VARCHAR(50) DEFAULT NULL, CHANGE priority priority ENUM(\'LOW\', \'MEDIUM\', \'HIGH\') DEFAULT \'MEDIUM\', CHANGE created_at created_at DATETIME DEFAULT CURRENT_TIMESTAMP, CHANGE updated_at updated_at DATETIME DEFAULT CURRENT_TIMESTAMP, CHANGE project_id project_id BIGINT UNSIGNED NOT NULL');
        $this->addSql('ALTER TABLE transaction DROP INDEX reference, ADD UNIQUE INDEX reference (reference)');
        $this->addSql('ALTER TABLE transaction DROP FOREIGN KEY FK_723705D12797E36F');
        $this->addSql('ALTER TABLE transaction CHANGE id id BIGINT AUTO_INCREMENT NOT NULL, CHANGE reference reference VARCHAR(50) DEFAULT NULL, CHANGE cost cost NUMERIC(15, 2) NOT NULL, CHANGE expense_category expense_category VARCHAR(100) DEFAULT NULL, CHANGE description description INT NOT NULL, CHANGE project_budget_id project_budget_id BIGINT NOT NULL');
        $this->addSql('ALTER TABLE transaction ADD CONSTRAINT `fk_transaction_project_budget` FOREIGN KEY (project_budget_id) REFERENCES project_budget (id) ON UPDATE CASCADE ON DELETE CASCADE');
        $this->addSql('ALTER TABLE transaction RENAME INDEX idx_723705d12797e36f TO fk_transaction_project_budget');
        $this->addSql('ALTER TABLE utilisateurs CHANGE nom nom VARCHAR(50) NOT NULL, CHANGE prenom prenom VARCHAR(50) NOT NULL, CHANGE email email VARCHAR(150) NOT NULL, CHANGE telephone telephone VARCHAR(20) DEFAULT NULL, CHANGE role role VARCHAR(50) NOT NULL, CHANGE departement departement VARCHAR(100) DEFAULT NULL, CHANGE statut statut VARCHAR(20) DEFAULT \'Pending\'');
    }
}
