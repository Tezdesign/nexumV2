<?php

require __DIR__ . '/vendor/autoload.php';

use App\Kernel;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\HttpFoundation\Request;

(new Dotenv())->bootEnv(__DIR__ . '/.env');

$kernel = new Kernel($_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']);
$kernel->boot();

$container = $kernel->getContainer();
$em = $container->get('doctrine.orm.entity_manager');

$draft = $em->getRepository(\App\Entity\FinancialAnalysis\ExpenseDraft::class)->findOneBy([]);

if ($draft) {
    echo "Found draft ID: " . $draft->getId() . "\n";
    $draft->setAmount($draft->getAmount() + 1); // Trigger an update
    $em->flush();
    echo "Flushed update.\n";
} else {
    echo "No draft found.\n";
}

$logs = $em->getConnection()->fetchAllAssociative('SELECT * FROM sync_log');
print_r($logs);

if (file_exists(__DIR__ . '/var/log/sync_listener_error.log')) {
    echo "Errors found in log:\n";
    echo file_get_contents(__DIR__ . '/var/log/sync_listener_error.log');
}
