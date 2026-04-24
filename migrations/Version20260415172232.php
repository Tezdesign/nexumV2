<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260415172232 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE expense_draft ADD eval_data JSON DEFAULT NULL, CHANGE project_budget_related_id project_budget_related_id BIGINT NOT NULL');
          }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE expense_draft DROP FOREIGN KEY FK_E57431AEB03A8386');
        $this->addSql('ALTER TABLE expense_draft DROP eval_data, CHANGE project_budget_related_id project_budget_related_id BIGINT NOT NULL');        $this->addSql('ALTER TABLE project_budget CHANGE id id BIGINT AUTO_INCREMENT NOT NULL, CHANGE total_budget total_budget NUMERIC(15, 2) NOT NULL, CHANGE actualSpend actualSpend NUMERIC(15, 2) NOT NULL, CHANGE status status VARCHAR(50) DEFAULT \'PENDING\', CHANGE projectId projectId BIGINT UNSIGNED NOT NULL');        $this->addSql('ALTER TABLE quiz RENAME INDEX idx_a412fa925200282e TO formation_id');        $this->addSql('ALTER TABLE resource_assignment CHANGE project_code project_code VARCHAR(50) NOT NULL, CHANGE client_code client_code VARCHAR(50) DEFAULT NULL, CHANGE total_cost total_cost NUMERIC(12, 2) DEFAULT NULL, CHANGE status status VARCHAR(50) DEFAULT \'ACTIVE\', CHANGE penalty_days_applied penalty_days_applied INT DEFAULT 0 NOT NULL, CHANGE bonus_applied bonus_applied TINYINT DEFAULT 0 NOT NULL, CHANGE user_id user_id INT NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX resource_code ON resources (resource_code)');        $this->addSql('ALTER TABLE transaction CHANGE id id BIGINT AUTO_INCREMENT NOT NULL, CHANGE reference reference VARCHAR(50) DEFAULT NULL, CHANGE cost cost NUMERIC(15, 2) NOT NULL, CHANGE expense_category expense_category VARCHAR(100) DEFAULT NULL, CHANGE project_budget_id project_budget_id BIGINT NOT NULL');
        $this->addSql('ALTER TABLE transaction ADD CONSTRAINT `fk_transaction_project_budget` FOREIGN KEY (project_budget_id) REFERENCES project_budget (id) ON UPDATE CASCADE ON DELETE CASCADE');        $this->addSql('ALTER TABLE utilisateurs CHANGE nom nom VARCHAR(50) NOT NULL, CHANGE prenom prenom VARCHAR(50) NOT NULL, CHANGE email email VARCHAR(150) NOT NULL, CHANGE telephone telephone VARCHAR(20) DEFAULT NULL, CHANGE role role VARCHAR(50) NOT NULL, CHANGE departement departement VARCHAR(100) DEFAULT NULL, CHANGE statut statut VARCHAR(20) DEFAULT \'Pending\'');
    }
}
