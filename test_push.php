<?php

require __DIR__.'/vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

// Load .env files manually
$dotenv = new Dotenv();
$dotenv->bootEnv(__DIR__.'/.env');

$credentialsPath = __DIR__ . '/nexum-aca87-firebase-adminsdk-fbsvc-476323cec1.json';
$devFcmToken = $_ENV['DEV_FCM_TOKEN'] ?? '';

echo "Checking configuration...\n";
if (!file_exists($credentialsPath)) {
    die("ERROR: Firebase JSON not found at $credentialsPath\n");
}
if (empty($devFcmToken)) {
    die("ERROR: DEV_FCM_TOKEN is empty in .env\n");
}

echo "Initializing Firebase...\n";
$factory = (new Factory)->withServiceAccount($credentialsPath);
$messaging = $factory->createMessaging();

echo "Sending push to token: " . substr($devFcmToken, 0, 15) . "...\n";

try {
    $notification = Notification::create('Test Push', 'This is a test notification from root script.');
    $message = CloudMessage::withTarget('token', $devFcmToken)->withNotification($notification);

    $messaging->send($message);
    echo "SUCCESS: Push notification sent to FCM servers!\n";
} catch (\Exception $e) {
    echo "FAILED: " . $e->getMessage() . "\n";
}
