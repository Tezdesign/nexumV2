git <?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Corrected to resolve Foreign Key mismatch.
 */
final class Version20260416220000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Fixes incompatible FK column for transaction table and updates project schemas.';
    }

    public function up(Schema $schema): void
    {
        // 1. Message Attachments Index
        $this->addSql('CREATE INDEX index_attachment_message ON message_attachments (message_id)');

        // 2. Project File Updates
        $this->addSql('ALTER TABLE project_file ADD project_id INT NOT NULL, ADD uploaded_by INT NOT NULL, ADD original_name VARCHAR(255) NOT NULL, ADD public_id VARCHAR(255) NOT NULL, ADD resource_type VARCHAR(50) NOT NULL, ADD format VARCHAR(50) DEFAULT NULL, ADD bytes INT DEFAULT NULL, ADD secure_url VARCHAR(1000) NOT NULL, ADD created_at DATETIME DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_project_file_project ON project_file (project_id)');
        $this->addSql('CREATE INDEX idx_project_file_uploaded_by ON project_file (uploaded_by)');

        // 3. Quiz Updates
        $this->addSql('ALTER TABLE quiz CHANGE question question LONGTEXT NOT NULL, CHANGE r1 r1 VARCHAR(255) NOT NULL, CHANGE r2 r2 VARCHAR(255) NOT NULL, CHANGE r3 r3 VARCHAR(255) NOT NULL, CHANGE formation_id formation_id INT NOT NULL');

        // 4. Reclamation PK Change
        $this->addSql('ALTER TABLE reclamation MODIFY id_rec INT NOT NULL');
        $this->addSql('ALTER TABLE reclamation CHANGE id_rec idRec INT AUTO_INCREMENT NOT NULL, DROP PRIMARY KEY, ADD PRIMARY KEY (idRec)');

        // 5. Resources Updates
        $this->addSql('ALTER TABLE resources CHANGE resource_code resource_code VARCHAR(50) NOT NULL, CHANGE resource_type resource_type VARCHAR(50) NOT NULL, CHANGE status status VARCHAR(50) DEFAULT NULL');

        // 6. Resultat Updates
        $this->addSql('ALTER TABLE resultat ADD user_id INT NOT NULL, CHANGE date_passage date_passage DATETIME NOT NULL');
        $this->addSql('CREATE INDEX IDX_E7DB5DE2A76ED395 ON resultat (user_id)');

        // 7. TRANSACTION FIX: Ensure the column type matches the reference exactly
        // If your project_budget.id is a BIGINT, change 'INT' below to 'BIGINT'
        $this->addSql('ALTER TABLE transaction MODIFY project_budget_id INT NOT NULL');
        $this->addSql('ALTER TABLE transaction ADD CONSTRAINT FK_723705D12797E36F FOREIGN KEY (project_budget_id) REFERENCES project_budget (id)');
    }

    public function down(Schema $schema): void
    {
        // Reverse Transaction changes
        $this->addSql('ALTER TABLE transaction DROP FOREIGN KEY FK_723705D12797E36F');

        // Reverse Resultat changes
        $this->addSql('ALTER TABLE resultat DROP FOREIGN KEY FK_E7DB5DE2A76ED395');
        $this->addSql('ALTER TABLE resultat DROP user_id, CHANGE date_passage date_passage DATETIME DEFAULT NULL');

        // Reverse Resources changes
        $this->addSql('ALTER TABLE resources CHANGE resource_code resource_code VARCHAR(255) NOT NULL, CHANGE resource_type resource_type VARCHAR(255) NOT NULL, CHANGE status status VARCHAR(255) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX resource_code ON resources (resource_code)');

        // Reverse Reclamation changes
        $this->addSql('ALTER TABLE reclamation MODIFY idRec INT NOT NULL');

        // Reverse Quiz changes
        $this->addSql('ALTER TABLE quiz CHANGE question question LONGTEXT DEFAULT NULL, CHANGE r1 r1 VARCHAR(255) DEFAULT NULL, CHANGE r2 r2 VARCHAR(255) DEFAULT NULL, CHANGE r3 r3 VARCHAR(255) DEFAULT NULL, CHANGE formation_id formation_id INT DEFAULT NULL');

        // Reverse Project File changes
        $this->addSql('DROP INDEX idx_project_file_project ON project_file');
        $this->addSql('DROP INDEX idx_project_file_uploaded_by ON project_file');
        $this->addSql('ALTER TABLE project_file DROP project_id, DROP uploaded_by, DROP original_name, DROP public_id, DROP resource_type, DROP format, DROP bytes, DROP secure_url, DROP created_at');

        // Reverse Budget Profile changes (from your down() snippet)
        $this->addSql('ALTER TABLE budget_profile CHANGE id id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL, CHANGE fiscal_year fiscal_year DATE NOT NULL, CHANGE budget_disposable budget_disposable NUMERIC(15, 2) NOT NULL, CHANGE total_expense total_expense NUMERIC(15, 2) DEFAULT \'0.00\', CHANGE margin_profit margin_profit FLOAT DEFAULT \'0\'');
    }
}