<?php

require_once 'vendor/autoload.php';

use Symfony\Component\HttpClient\HttpClient;
use App\Service\TelegramNotificationService;
use Symfony\Component\Dotenv\Dotenv;

// Load environment variables from .env file
$dotenv = new Dotenv();
$dotenv->loadEnv(__DIR__.'/.env');

echo "=== Telegram Bot Test ===\n\n";

// Check if environment variables are set
$botToken = $_ENV['TELEGRAM_BOT_TOKEN'] ?? '';
$chatId = $_ENV['TELEGRAM_CHAT_ID'] ?? '';

if (empty($botToken)) {
    echo "❌ TELEGRAM_BOT_TOKEN is not set\n";
    echo "Please set the environment variable or add it to your .env file\n";
    exit(1);
}

if (empty($chatId)) {
    echo "❌ TELEGRAM_CHAT_ID is not set\n";
    echo "Please set the environment variable or add it to your .env file\n";
    exit(1);
}

echo "✅ Configuration found:\n";
echo "   Bot Token: " . substr($botToken, 0, 10) . "...\n";
echo "   Chat ID: {$chatId}\n\n";

// Create the service
$httpClient = HttpClient::create();
$telegramService = new TelegramNotificationService($httpClient, $botToken, $chatId);

// Test basic message sending
echo "1. Testing basic message sending...\n";
$testMessage = "🤖 Test message from Nexum application - " . date('Y-m-d H:i:s');
$success = $telegramService->sendMessage($chatId, $testMessage);

if ($success) {
    echo "✅ Message sent successfully!\n";
} else {
    echo "❌ Failed to send message\n";
    echo "   Check your bot token and chat ID\n";
    echo "   Make sure the bot is running and has permission to send messages\n";
}

// Test reclamation notification
echo "\n2. Testing reclamation notification...\n";
$reclamationSuccess = $telegramService->notifyNewReclamation(
    'John Doe',
    'john.doe@example.com',
    'Test Reclamation Title'
);

if ($reclamationSuccess) {
    echo "✅ Reclamation notification sent successfully!\n";
} else {
    echo "❌ Failed to send reclamation notification\n";
}

echo "\n=== Test Complete ===\n";
