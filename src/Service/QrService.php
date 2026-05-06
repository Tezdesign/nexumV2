<?php

namespace App\Service;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;

class QrService
{
    private string $publicDir;

    public function __construct(string $projectDir)
    {
        $this->publicDir = rtrim($projectDir, DIRECTORY_SEPARATOR) . '/public/uploads/quiz_ai';
    }

    /**
     * Génère un QR code PNG et retourne le chemin absolu du fichier.
     * Retourne null en cas d'échec.
     */
    public function generate(string $data): ?string
    {
        error_log('[QrService] ===== generate entered =====');

        if (!extension_loaded('gd')) {
            error_log('[QrService] ERROR: extension GD non activée');
            return null;
        }

        if (!is_dir($this->publicDir)) {
            error_log('[QrService] Creating QR directory: ' . $this->publicDir);

            if (!mkdir($this->publicDir, 0775, true) && !is_dir($this->publicDir)) {
                error_log('[QrService] ERROR: impossible de créer le dossier QR: ' . $this->publicDir);
                return null;
            }
        }

        if (!is_writable($this->publicDir)) {
            error_log('[QrService] ERROR: dossier QR non accessible en écriture: ' . $this->publicDir);
            return null;
        }

        $filename = 'quiz_ai_' . uniqid('', true) . '.png';
        $filePath = $this->publicDir . DIRECTORY_SEPARATOR . $filename;

        error_log('[QrService] Output file path: ' . $filePath);
        error_log('[QrService] Data length: ' . strlen($data));

        try {
            $builder = new Builder(
                writer: new PngWriter(),
                writerOptions: [],
                validateResult: false,
                data: $data,
                encoding: new Encoding('UTF-8'),
                errorCorrectionLevel: ErrorCorrectionLevel::High,
                size: 300,
                margin: 10,
                roundBlockSizeMode: RoundBlockSizeMode::Margin
            );

            $result = $builder->build();
            $result->saveToFile($filePath);

            clearstatcache(true, $filePath);

            if (!is_file($filePath)) {
                throw new \RuntimeException('QR file not created');
            }

            $size = filesize($filePath);
            if ($size === false || $size === 0) {
                throw new \RuntimeException('QR file is empty');
            }

            error_log('[QrService] QR generated successfully: ' . $filePath . ' (' . $size . ' bytes)');

            return $filePath;

        } catch (\Throwable $e) {
            error_log('[QrService] ERROR: Échec génération QR: ' . $e->getMessage());
            error_log('[QrService] Exception type: ' . get_class($e));
            error_log('[QrService] Trace: ' . $e->getTraceAsString());

            return null;
        }
    }

    /**
     * Retourne l'URL publique d'un fichier QR à partir de son chemin absolu.
     */
    public function getPublicUrl(string $filePath): string
    {
        $filename = basename($filePath);
        return '/uploads/quiz_ai/' . $filename;
    }
}

