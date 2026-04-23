<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260412120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Increase message attachment storage to LONGBLOB for 30 MB uploads.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE message_attachments CHANGE data data LONGBLOB NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE message_attachments CHANGE data data BLOB NOT NULL');
    }
}