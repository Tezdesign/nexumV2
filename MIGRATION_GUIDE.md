# Doctrine Migration Guide for Nexum (Legacy Database)

**Please read this before running `make:migration`!**

Because we are migrating an existing Java database into Symfony, our live database already has its own naming conventions for foreign keys, indexes, and certain column types. 

Doctrine is extremely strict and prefers its own auto-generated names (e.g., `IDX_723705D12797E36F` instead of `fk_transaction_project_budget`).

### The Issue
If you run `php bin/console make:migration`, Doctrine will generate SQL for your *new feature*, but it will **also** sneak in SQL to rename our legacy Java foreign keys and tweak column formats. 

If you blindly run that migration, you will unnecessarily modify the legacy database structure.

### The Automated Workflow (Recommended)
Because we expect Doctrine to generate this "legacy noise", we have created an automated script to clean it up for you! Whenever you add a new column or entity, follow these exact steps:

1. **Update your PHP Entity:** Add your new property (e.g., `private ?string $description`).
2. **Generate the Migration:** Run `php bin/console make:migration`.
3. **Clean the Migration:** Run the automated cleaner script:
   ```bash
   php bin/clean_migration.php
   ```
   *This script scans your newest migration and automatically deletes any `DROP INDEX`, `RENAME INDEX`, or `DROP FOREIGN KEY` commands that try to overwrite the Java conventions!*
4. **REVIEW THE MIGRATION FILE:** Always take a quick look inside the generated `migrations/VersionXYZ.php` file to make sure your actual feature (e.g., `ALTER TABLE tasks ADD description...`) is still there and correct.
5. **Execute:** Run `php bin/console doctrine:migrations:migrate`.

By manually filtering the generated migrations, we keep our database perfectly in sync with our new features while telling Doctrine to leave the legacy Java architecture alone.
