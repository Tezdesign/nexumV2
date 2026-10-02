# Migrations

## Overview

Doctrine migrations for a database that was first built by the Java desktop app. The live schema keeps Java naming for foreign keys, indexes and some column types, so Doctrine's diff always contains noise.

## Key files

| File | Owns |
|---|---|
| `../bin/clean_migration.php` | Strips legacy noise (`DROP INDEX`, `RENAME INDEX`, `DROP FOREIGN KEY`) from the newest migration |
| `../bin/clean_participer_migration.php` | One off cleaner for the `participer` table |
| `../MIGRATION_GUIDE.md` | The full written workflow |
| `../fix_indexes.php`, `../src/Command/SchemaDiffCommand.php` | Helpers for inspecting schema drift |

## Conventions

Workflow after changing an entity:

```bash
php bin/console make:migration
php bin/clean_migration.php
# open the new migrations/Version*.php and confirm your real change is still there
php bin/console doctrine:migrations:migrate
```

- Never run `doctrine:schema:update`. It would rewrite legacy keys.
- Review every generated file by hand. The cleaner only targets legacy tables, so new tables pass through untouched.

## Gotchas

- Both apps write to the same tables. A column rename or type change here breaks the Java app, so treat schema changes as cross app changes.
- The test suite uses SQLite and builds tables from entity metadata, so it never exercises these migrations.

_Drafted by /audit from the repo, worth a quick human pass. Edit freely: once a line stops matching this draft, later runs treat it as curated and will flag rather than overwrite it._
