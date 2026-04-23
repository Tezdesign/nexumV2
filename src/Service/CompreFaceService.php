<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\HttpFoundation\File\File;

class CompreFaceService
{
    private const API_KEY = '751f3a42-3684-4c35-aa0b-4aff7b67cda0';
    private const BASE_URL = 'http://localhost:8000/api/v1/recognition';
    
    private HttpClientInterface $httpClient;
    
    public function __construct(HttpClientInterface $httpClient)
    {
        $this->httpClient = $httpClient;
    }
    
    public function registerFace(string $imageData, string $subject): ?string
    {
        try {
            $boundary = uniqid();
            
            $body = "--{$boundary}\r\n";
            $body .= "Content-Disposition: form-data; name=\"file\"; filename=\"face.jpg\"\r\n";
            $body .= "Content-Type: image/jpeg\r\n\r\n";
            $body .= $imageData . "\r\n";
            $body .= "--{$boundary}--\r\n";
            
            $response = $this->httpClient->request('POST', self::BASE_URL . '/faces?subject=' . urlencode($subject), [
                'headers' => [
                    'x-api-key' => self::API_KEY,
                    'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
                ],
                'body' => $body,
            ]);
            
            $statusCode = $response->getStatusCode();
            $responseBody = $response->getContent();
            
            if ($statusCode === 200 || $statusCode === 201) {
                $data = json_decode($responseBody, true);
                
                // La réponse peut contenir "image_id" ou "face_token" selon la version
                if (isset($data['image_id'])) {
                    return $data['image_id'];
                } elseif (isset($data['face_token'])) {
                    return $data['face_token'];
                }
            }
        } catch (\Exception $e) {
            // Log error instead of echoing
            error_log('CompreFace registerFace error: ' . $e->getMessage());
        }
        
        return null;
    }
    
    public function recognizeFace(string $imageData): ?string
    {
        try {
            $boundary = uniqid();
            
            $body = "--{$boundary}\r\n";
            $body .= "Content-Disposition: form-data; name=\"file\"; filename=\"face.jpg\"\r\n";
            $body .= "Content-Type: image/jpeg\r\n\r\n";
            $body .= $imageData . "\r\n";
            $body .= "--{$boundary}--\r\n";
            
            $response = $this->httpClient->request('POST', self::BASE_URL . '/recognize', [
                'headers' => [
                    'x-api-key' => self::API_KEY,
                    'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
                ],
                'body' => $body,
            ]);
            
            $statusCode = $response->getStatusCode();
            $responseBody = $response->getContent();
            
            if ($statusCode === 200) {
                $data = json_decode($responseBody, true);
                
                if (isset($data['result'][0]['subjects'][0]['subject'])) {
                    return $data['result'][0]['subjects'][0]['subject'];
                }
            }
        } catch (\Exception $e) {
            // Log error instead of echoing
            error_log('CompreFace recognizeFace error: ' . $e->getMessage());
        }
        
        return null;
    }
    
    /**
     * Alternative method using File object instead of raw image data
     */
    public function registerFaceFromFile(File $file, string $subject): ?string
    {
        try {
            $imageData = file_get_contents($file->getPathname());
            return $this->registerFace($imageData, $subject);
        } catch (\Exception $e) {
            echo "Exception: " . $e->getMessage() . "\n";
            return null;
        }
    }
    
    /**
     * Alternative method using File object instead of raw image data
     */
    public function recognizeFaceFromFile(File $file): ?string
    {
        try {
            $imageData = file_get_contents($file->getPathname());
            return $this->recognizeFace($imageData);
        } catch (\Exception $e) {
            echo "Exception: " . $e->getMessage() . "\n";
            return null;
        }
    }
}
