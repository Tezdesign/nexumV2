<?php

use App\Kernel;
use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

(new Dotenv())->bootEnv(dirname(__DIR__).'/.env');

$kernel = new Kernel($_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']);
$kernel->boot();

$container = $kernel->getContainer();

$params = $container->get('parameter_bag');
$logger = $container->get('logger');

$firebaseService = new \App\Service\FirebaseNotificationService($params, $logger);

echo "Attempting to send push notification...\n";

$result = $firebaseService->sendPushNotification(
    'Test Push',
    'This is a test notification from the standalone script.'
);

if ($result) {
    echo "SUCCESS: Push notification sent.\n";
} else {
    echo "FAILED: Check var/log/dev.log or prod.log for errors.\n";
}
