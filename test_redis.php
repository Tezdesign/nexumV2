<?php
require __DIR__.'/vendor/autoload.php';
use Symfony\Component\Dotenv\Dotenv;

// Load .env and .env.local
$dotenv = new Dotenv();
$dotenv->bootEnv(__DIR__.'/.env');

// Determine which URL to use via CLI argument or default to standard REDIS_URL
$envVarToUse = isset($argv[1]) && $argv[1] === 'house' ? 'REDIS_URL_HOUSE' : 'REDIS_URL';
$url = $_ENV[$envVarToUse] ?? null;

if (!$url) {
    die("ERROR: $envVarToUse is not set in your .env or .env.local files.\n");
}

echo "Testing connection to: $url\n";

$parsed = parse_url($url);
$host = $parsed['host'] ?? '127.0.0.1';
$port = $parsed['port'] ?? 6379;
$pass = isset($parsed['pass']) ? $parsed['pass'] : '';

echo "Connecting to $host:$port via Predis...\n";

try {
    $clientParams = [
        'scheme' => 'tcp',
        'host'   => $host,
        'port'   => $port,
        'timeout' => 2.5,
    ];
    
    if (!empty($pass)) {
        $clientParams['password'] = $pass;
    }

    $redis = new \Predis\Client($clientParams);
    
    // Predis connects lazily, so we force a command to test the connection
    $redis->ping();

    echo "SUCCESS: Connected and authenticated to Redis!\n";
    
    echo "Testing Read/Write...\n";
    $redis->set('ping_test', 'pong');
    $val = $redis->get('ping_test');
    
    if ($val === 'pong') {
        echo "SUCCESS: Read/Write test passed! Cache is working.\n";
    } else {
        echo "FAILED: Cache value did not match.\n";
    }
    
    $redis->del('ping_test');
    
} catch (Exception $e) {
    echo "CRITICAL ERROR: " . $e->getMessage() . "\n";
}
