<?php

require_once 'vendor/autoload.php';

use Symfony\Component\HttpClient\HttpClient;
use App\Service\CompreFaceService;

// Create a simple test image (1x1 pixel JPEG for testing)
$testImageData = base64_decode('/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQH/2wBDAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQH/wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAv/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwCdABmX/9k=');

echo "=== CompreFace Service Test ===\n\n";

// Create the service with real HTTP client
$httpClient = HttpClient::create();
$compreFaceService = new CompreFaceService($httpClient);

echo "1. Testing registerFace method...\n";
$subject = 'test_user_' . time();
$faceId = $compreFaceService->registerFace($testImageData, $subject);

if ($faceId) {
    echo "✓ Face registered successfully with ID: $faceId\n";
} else {
    echo "✗ Face registration failed (make sure CompreFace server is running on localhost:8000)\n";
}

echo "\n2. Testing recognizeFace method...\n";
$recognizedSubject = $compreFaceService->recognizeFace($testImageData);

if ($recognizedSubject) {
    echo "✓ Face recognized as: $recognizedSubject\n";
} else {
    echo "✗ Face recognition failed (no match or server unavailable)\n";
}

echo "\n=== Test Complete ===\n";
