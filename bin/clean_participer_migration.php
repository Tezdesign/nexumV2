<?php

$migrationsDir = __DIR__ . '/../migrations';
$files = glob($migrationsDir . '/Version*.php');

if (empty($files)) {
    die("No migrations found.\n");
}

rsort($files);
$latestMigration = $files[0];
$fileName = basename($latestMigration);

echo "Scanning latest migration: $fileName for participer and referenced tables...\n";

$content = file_get_contents($latestMigration);
$originalContent = $content;

// Tables to omit changes for (participer and referenced tables)
$tablesToOmit = [
    'participer',
    'formation',
    'utilisateurs'
];

foreach ($tablesToOmit as $table) {
    // Match any $this->addSql(...) that contains the table name
    $pattern = "/\s*\\\$this->addSql\('[^']*\\b" . $table . "\\b[^']*'\);/i";
    $content = preg_replace($pattern, "", $content);
}

// Clean up any empty line gaps left by the removal
$content = preg_replace("/\n\s*\n\s*\n/", "\n\n", $content);

if ($content !== $originalContent) {
    file_put_contents($latestMigration, $content);
    echo "✅ Successfully removed changes related to participer, formation, and utilisateurs from $fileName.\n";
} else {
    echo "👍 No relevant changes found to clean.\n";
}
