<?php

namespace App\Tests\Service;

use App\Service\CompreFaceService;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class CompreFaceServiceTest extends WebTestCase
{
    private CompreFaceService $compreFaceService;
    private MockHttpClient $mockHttpClient;

    protected function setUp(): void
    {
        $this->mockHttpClient = new MockHttpClient();
        $this->compreFaceService = new CompreFaceService($this->mockHttpClient);
    }

    public function testRegisterFaceSuccess(): void
    {
        // Mock successful response
        $this->mockHttpClient->setResponseFactory([
            new MockResponse(json_encode([
                'image_id' => 'test-image-id-123'
            ]), [
                'http_code' => 201,
                'response_headers' => ['Content-Type' => 'application/json']
            ])
        ]);

        $testImageData = 'fake-image-data-for-testing';
        $subject = 'test-user';

        $result = $this->compreFaceService->registerFace($testImageData, $subject);

        $this->assertEquals('test-image-id-123', $result);
    }

    public function testRegisterFaceWithFaceToken(): void
    {
        // Mock response with face_token instead of image_id
        $this->mockHttpClient->setResponseFactory([
            new MockResponse(json_encode([
                'face_token' => 'test-face-token-456'
            ]), [
                'http_code' => 200,
                'response_headers' => ['Content-Type' => 'application/json']
            ])
        ]);

        $testImageData = 'fake-image-data-for-testing';
        $subject = 'test-user';

        $result = $this->compreFaceService->registerFace($testImageData, $subject);

        $this->assertEquals('test-face-token-456', $result);
    }

    public function testRegisterFaceFailure(): void
    {
        // Mock error response
        $this->mockHttpClient->setResponseFactory([
            new MockResponse(json_encode([
                'error' => 'Invalid image format'
            ]), [
                'http_code' => 400,
                'response_headers' => ['Content-Type' => 'application/json']
            ])
        ]);

        $testImageData = 'invalid-image-data';
        $subject = 'test-user';

        $result = $this->compreFaceService->registerFace($testImageData, $subject);

        $this->assertNull($result);
    }

    public function testRecognizeFaceSuccess(): void
    {
        // Mock successful recognition response
        $this->mockHttpClient->setResponseFactory([
            new MockResponse(json_encode([
                'result' => [
                    [
                        'subjects' => [
                            ['subject' => 'john_doe', 'similarity' => 0.95],
                            ['subject' => 'jane_doe', 'similarity' => 0.87]
                        ]
                    ]
                ]
            ]), [
                'http_code' => 200,
                'response_headers' => ['Content-Type' => 'application/json']
            ])
        ]);

        $testImageData = 'fake-image-data-for-testing';

        $result = $this->compreFaceService->recognizeFace($testImageData);

        $this->assertEquals('john_doe', $result);
    }

    public function testRecognizeFaceNoMatch(): void
    {
        // Mock response with no matches
        $this->mockHttpClient->setResponseFactory([
            new MockResponse(json_encode([
                'result' => [
                    [
                        'subjects' => []
                    ]
                ]
            ]), [
                'http_code' => 200,
                'response_headers' => ['Content-Type' => 'application/json']
            ])
        ]);

        $testImageData = 'fake-image-data-for-testing';

        $result = $this->compreFaceService->recognizeFace($testImageData);

        $this->assertNull($result);
    }

    public function testRecognizeFaceFailure(): void
    {
        // Mock error response
        $this->mockHttpClient->setResponseFactory([
            new MockResponse(json_encode([
                'error' => 'Service unavailable'
            ]), [
                'http_code' => 503,
                'response_headers' => ['Content-Type' => 'application/json']
            ])
        ]);

        $testImageData = 'fake-image-data-for-testing';

        $result = $this->compreFaceService->recognizeFace($testImageData);

        $this->assertNull($result);
    }

    public function testRegisterFaceWithEmptyData(): void
    {
        // Test with empty image data
        $this->mockHttpClient->setResponseFactory([
            new MockResponse(json_encode([
                'error' => 'No image data provided'
            ]), [
                'http_code' => 400,
                'response_headers' => ['Content-Type' => 'application/json']
            ])
        ]);

        $emptyImageData = '';
        $subject = 'test-user';

        $result = $this->compreFaceService->registerFace($emptyImageData, $subject);

        $this->assertNull($result);
    }
}
