<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260416210128 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE project_file (id INT AUTO_INCREMENT NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE budget_profile DROP INDEX fiscal_year, ADD INDEX fiscal_year (fiscal_year)');
        $this->addSql('ALTER TABLE budget_profile ADD base_currency VARCHAR(3) DEFAULT NULL, ADD start_date DATE DEFAULT NULL, ADD end_date DATE DEFAULT NULL, ADD status VARCHAR(50) DEFAULT \'DRAFT\' NOT NULL, CHANGE id id INT AUTO_INCREMENT NOT NULL, CHANGE fiscal_year fiscal_year VARCHAR(255) NOT NULL, CHANGE budget_disposable budget_disposable NUMERIC(10, 2) NOT NULL, CHANGE total_expense total_expense NUMERIC(10, 2) DEFAULT NULL, CHANGE margin_profit margin_profit DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('CREATE INDEX index_attachment_message ON message_attachments (message_id)');        $this->addSql('ALTER TABLE project_budget ADD CONSTRAINT FK_64561C316C9360F7 FOREIGN KEY (projectId) REFERENCES projects (id)');
        $this->addSql('ALTER TABLE quiz CHANGE question question LONGTEXT NOT NULL, CHANGE r1 r1 VARCHAR(255) NOT NULL, CHANGE r2 r2 VARCHAR(255) NOT NULL, CHANGE r3 r3 VARCHAR(255) NOT NULL, CHANGE formation_id formation_id INT NOT NULL');        $this->addSql('ALTER TABLE reclamation MODIFY id_rec INT NOT NULL');
        $this->addSql('ALTER TABLE reclamation CHANGE id_rec idRec INT AUTO_INCREMENT NOT NULL, DROP PRIMARY KEY, ADD PRIMARY KEY (idRec)');        $this->addSql('ALTER TABLE resources CHANGE resource_code resource_code VARCHAR(50) NOT NULL, CHANGE resource_type resource_type VARCHAR(50) NOT NULL, CHANGE status status VARCHAR(50) DEFAULT NULL');
        $this->addSql('ALTER TABLE resultat ADD user_id INT NOT NULL, CHANGE date_passage date_passage DATETIME NOT NULL');
        $this->addSql('CREATE INDEX IDX_E7DB5DE2A76ED395 ON resultat (user_id)');        $this->addSql('ALTER TABLE transaction CHANGE description description VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE transaction ADD CONSTRAINT FK_723705D12797E36F FOREIGN KEY (project_budget_id) REFERENCES project_budget (id)');    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE project_file');
        $this->addSql('ALTER TABLE budget_profile DROP INDEX fiscal_year, ADD UNIQUE INDEX fiscal_year (fiscal_year)');
        $this->addSql('ALTER TABLE budget_profile DROP base_currency, DROP start_date, DROP end_date, DROP status, CHANGE id id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL, CHANGE fiscal_year fiscal_year DATE NOT NULL, CHANGE budget_disposable budget_disposable NUMERIC(15, 2) NOT NULL, CHANGE total_expense total_expense NUMERIC(15, 2) DEFAULT \'0.00\', CHANGE margin_profit margin_profit FLOAT DEFAULT \'0\'');        $this->addSql('ALTER TABLE project_budget CHANGE id id BIGINT AUTO_INCREMENT NOT NULL, CHANGE total_budget total_budget NUMERIC(15, 2) NOT NULL, CHANGE actualSpend actualSpend NUMERIC(15, 2) NOT NULL, CHANGE status status VARCHAR(50) DEFAULT \'PENDING\', CHANGE projectId projectId BIGINT UNSIGNED NOT NULL');        $this->addSql('ALTER TABLE quiz CHANGE question question LONGTEXT DEFAULT NULL, CHANGE r1 r1 VARCHAR(255) DEFAULT NULL, CHANGE r2 r2 VARCHAR(255) DEFAULT NULL, CHANGE r3 r3 VARCHAR(255) DEFAULT NULL, CHANGE formation_id formation_id INT DEFAULT NULL');        $this->addSql('ALTER TABLE reclamation MODIFY idRec INT NOT NULL');        $this->addSql('ALTER TABLE resources CHANGE resource_code resource_code VARCHAR(255) NOT NULL, CHANGE resource_type resource_type VARCHAR(255) NOT NULL, CHANGE status status VARCHAR(255) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX resource_code ON resources (resource_code)');        $this->addSql('ALTER TABLE resultat DROP FOREIGN KEY FK_E7DB5DE2A76ED395');        $this->addSql('ALTER TABLE resultat DROP user_id, CHANGE date_passage date_passage DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE resultat RENAME INDEX idx_e7db5de25200282e TO formation_id');        $this->addSql('ALTER TABLE transaction CHANGE description description INT NOT NULL');    }
}
