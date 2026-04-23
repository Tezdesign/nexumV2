<?php

namespace App\Service\Project;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class ProjectFileStorage
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $cloudName,
        private readonly string $apiKey,
        private readonly string $apiSecret,
    ) {
    }

    /**
     * @return array{
     *     public_id: string,
     *     resource_type: string,
     *     format: ?string,
     *     bytes: ?int,
     *     secure_url: string
     * }
     */
    public function upload(UploadedFile $file, int $projectId): array
    {
        $timestamp = time();
        $folder = 'nexum/projects/' . max(1, $projectId);
        $signature = $this->signUpload($folder, $timestamp);

        $response = $this->httpClient->request('POST', sprintf(
            'https://api.cloudinary.com/v1_1/%s/raw/upload',
            rawurlencode($this->cloudName)
        ), [
            'body' => [
                'file' => fopen($file->getRealPath(), 'r'),
                'api_key' => $this->apiKey,
                'timestamp' => (string) $timestamp,
                'signature' => $signature,
                'folder' => $folder,
                'use_filename' => 'true',
                'unique_filename' => 'true',
                'overwrite' => 'false',
            ],
        ]);

        $data = $response->toArray(false);
        $statusCode = $response->getStatusCode();

        if ($statusCode < 200 || $statusCode >= 300 || !isset($data['public_id'], $data['secure_url'])) {
            $message = is_array($data['error'] ?? null)
                ? (string) ($data['error']['message'] ?? 'Cloudinary upload failed.')
                : 'Cloudinary upload failed.';

            throw new \RuntimeException($message);
        }

        return [
            'public_id' => (string) $data['public_id'],
            'resource_type' => (string) ($data['resource_type'] ?? 'raw'),
            'format' => isset($data['format']) ? (string) $data['format'] : null,
            'bytes' => isset($data['bytes']) ? (int) $data['bytes'] : null,
            'secure_url' => (string) $data['secure_url'],
        ];
    }

    private function signUpload(string $folder, int $timestamp): string
    {
        $toSign = sprintf('folder=%s&overwrite=false&timestamp=%d&unique_filename=true&use_filename=true%s', $folder, $timestamp, $this->apiSecret);

        return sha1($toSign);
    }
}
