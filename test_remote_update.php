<?php
require __DIR__ . '/vendor/autoload.php';

use App\Kernel;
use Symfony\Component\Dotenv\Dotenv;

(new Dotenv())->bootEnv(__DIR__ . '/.env');

$kernel = new Kernel($_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']);
$kernel->boot();

$container = $kernel->getContainer();
$registry = $container->get('doctrine');

$localConn = $registry->getConnection('default');
$remoteConn = $registry->getConnection('remote');

$localRecord = $localConn->fetchAssociative("SELECT * FROM expense_draft LIMIT 1");
if ($localRecord) {
    echo "Found local record ID: " . $localRecord['id'] . "\n";
    $remoteExists = $remoteConn->fetchOne("SELECT id FROM expense_draft WHERE id = ?", [$localRecord['id']]);
    echo "Exists on remote? " . ($remoteExists ? "Yes\n" : "No\n");

    try {
        if ($remoteExists) {
            $remoteConn->update('expense_draft', $localRecord, ['id' => $localRecord['id']]);
            echo "Updated remote successfully.\n";
        } else {
            $remoteConn->insert('expense_draft', $localRecord);
            echo "Inserted remote successfully.\n";
        }
    } catch (\Throwable $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
} else {
    echo "No local records.\n";
}
