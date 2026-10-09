<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Records who made each formation rating, and allows one rating per user and formation.
 * Existing ratings keep a NULL user (several NULLs do not clash in the unique index).
 */
final class Version20261005090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'rating: add user_id and a unique (user_id, formation_id) index.';
    }

    public function up(Schema $schema): void
    {
        $this->skipIf(!$schema->hasTable('rating'), 'No rating table in this database.');

        $this->addSql('ALTER TABLE rating ADD user_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE rating ADD CONSTRAINT FK_RATING_USER FOREIGN KEY (user_id) REFERENCES utilisateurs (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_RATING_USER ON rating (user_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_rating_user_formation ON rating (user_id, formation_id)');
    }

    public function down(Schema $schema): void
    {
        $this->skipIf(!$schema->hasTable('rating'), 'No rating table in this database.');

        $this->addSql('ALTER TABLE rating DROP FOREIGN KEY FK_RATING_USER');
        $this->addSql('DROP INDEX uniq_rating_user_formation ON rating');
        $this->addSql('DROP INDEX IDX_RATING_USER ON rating');
        $this->addSql('ALTER TABLE rating DROP user_id');
    }
}
