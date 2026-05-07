<?php
require __DIR__.'/vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Cache\Adapter\RedisAdapter;

$dotenv = new Dotenv();
$dotenv->bootEnv(__DIR__.'/.env');

$url = $_ENV['REDIS_URL'] ?? null;
if (!$url) {
    die("REDIS_URL not set\n");
}

echo "Testing Symfony Redis Cache Adapter Append Behavior...\n";

// Emulate exactly what cache.yaml does:
$client = RedisAdapter::createConnection($url);
// Pass the exact same prefix_seed we set in cache.yaml
$cache = new RedisAdapter($client, 'nexum_app');

$key = 'test_notification_array_' . uniqid();

echo "1. Writing first notification...\n";
$item = $cache->getItem($key);
$data = $item->isHit() && is_array($item->get()) ? $item->get() : [];
array_unshift($data, ['id' => 1, 'msg' => 'First']);
$item->set($data);
$cache->save($item);

echo "2. Writing second notification...\n";
$item2 = $cache->getItem($key);
$data2 = $item2->isHit() && is_array($item2->get()) ? $item2->get() : [];
array_unshift($data2, ['id' => 2, 'msg' => 'Second']);
$item2->set($data2);
$cache->save($item2);

echo "3. Fetching final array...\n";
$item3 = $cache->getItem($key);
$data3 = $item3->isHit() ? $item3->get() : [];

echo "Final Array Count: " . count($data3) . "\n";
print_r($data3);

if (count($data3) === 2) {
    echo "\nSUCCESS: The Symfony Cache Adapter correctly appended and preserved history.\n";
} else {
    echo "\nFAILED: The array was smashed. Count is " . count($data3) . ".\n";
}
