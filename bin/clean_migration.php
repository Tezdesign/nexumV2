<?php

/**
 * Nexum Legacy Migration Cleaner
 *
 * This script automatically scans the latest Doctrine migration file
 * and removes "legacy noise" (SQL commands that try to overwrite
 * existing Java database conventions).
 *
 * It is specifically scoped ONLY to legacy tables so it does not
 * accidentally break migrations for newly created Symfony entities!
 */

$migrationsDir = __DIR__ . '/../migrations';
$files = glob($migrationsDir . '/Version*.php');

if (empty($files)) {
    die("No migrations found to clean.\n");
}

// Sort files to get the newest one
rsort($files);
$latestMigration = $files[0];
$fileName = basename($latestMigration);

echo "Scanning latest migration: $fileName...\n";

$content = file_get_contents($latestMigration);
$originalContent = $content;

/**
 * We only want to protect the existing Java tables from having their
 * custom foreign keys and indexes dropped or renamed by Doctrine.
 * Any table NOT in this list will be ignored by this cleaner.
 */
$legacyTables = [
    'budget_profile', 'conversations', 'conversation_participants',
    'messages', 'message_attachments', 'project_budget', 'transaction',
    'projects', 'project_assignments', 'resources', 'resource_assignment',
    'tasks', 'formation', 'participer', 'quiz', 'resultat',
    'reclamation', 'utilisateurs'
];
foreach ($legacyTables as $table) {
    // 1. Strip out DROP INDEX commands ONLY for legacy tables
    $content = preg_replace("/\s*\\\$this->addSql\('DROP INDEX [^']+ ON {$table}'\);\n/", "", $content);

    // 2. Strip out RENAME INDEX commands ONLY for legacy tables
    $content = preg_replace("/\s*\\\$this->addSql\('ALTER TABLE {$table} RENAME INDEX [^']+'\);\n/", "", $content);

    // 3. Strip out DROP FOREIGN KEY commands ONLY for legacy tables
    $content = preg_replace("/\s*\\\$this->addSql\('ALTER TABLE {$table} DROP FOREIGN KEY [^']+'\);\n/", "", $content);

    // 4. Strip out ALTER TABLE commands that try to change columns to INT (e.g. downgrading BIGINT IDs)
    $content = preg_replace("/\s*\\\$this->addSql\('ALTER TABLE {$table} CHANGE [^']+ INT[^']*'\);\n/i", "", $content);
}

if ($content !== $originalContent) {
    file_put_contents($latestMigration, $content);
    echo "✅ Successfully cleaned legacy noise from $fileName!\n";
    echo "   (New Symfony tables were left completely untouched.)\n";
} else {
    echo "👍 No legacy noise found. Migration looks clean.\n";
}
