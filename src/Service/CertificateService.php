<?php

namespace App\Service;

use Dompdf\Dompdf;

class CertificateService
{
    private string $projectDir;

    public function __construct(string $projectDir)
    {
        $this->projectDir = rtrim($projectDir, DIRECTORY_SEPARATOR);
    }

    public function generate($user, $formation, string $certificateId, ?string $qrPublicPath = null): string
    {
        error_log('[CertificateService] ===== generate entered =====');

        $userName = trim((string) $user->getNom());
        $formationTitle = trim((string) $formation->getTitre());

        if ($userName === '') {
            throw new \RuntimeException('Nom utilisateur vide dans CertificateService');
        }

        if ($formationTitle === '') {
            throw new \RuntimeException('Titre formation vide dans CertificateService');
        }

        error_log('[CertificateService] User: ' . $userName);
        error_log('[CertificateService] Formation: ' . $formationTitle);
        error_log('[CertificateService] Certificate ID: ' . $certificateId);
        error_log('[CertificateService] QR public path: ' . ($qrPublicPath ?? 'null'));

        $dompdf = new Dompdf();
        $dompdf->set_option('isRemoteEnabled', true);
        $dompdf->set_option('isHtml5ParserEnabled', true);

        $date = (new \DateTimeImmutable())->format('d M Y');

        $safeUserName = htmlspecialchars($userName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeFormationTitle = htmlspecialchars($formationTitle, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeCertificateId = htmlspecialchars($certificateId, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $qrBase64 = '';

        if ($qrPublicPath) {
            $qrAbsolutePath = $this->projectDir . '/public/' . ltrim($qrPublicPath, '/');
            error_log('[CertificateService] Looking for QR file at: ' . $qrAbsolutePath);

            if (is_file($qrAbsolutePath) && is_readable($qrAbsolutePath)) {
                $binary = file_get_contents($qrAbsolutePath);

                if ($binary === false) {
                    error_log('[CertificateService] WARNING: QR file exists but could not be read');
                } else {
                    $qrBase64 = base64_encode($binary);
                    error_log('[CertificateService] QR code embedded successfully');
                }
            } else {
                error_log('[CertificateService] WARNING: QR file not found or not readable');
            }
        } else {
            error_log('[CertificateService] No QR provided - generating certificate without QR');
        }

        $html = "
        <style>
            @page {
                margin: 20px;
                size: A4 landscape;
            }

            body {
                font-family: DejaVu Sans, sans-serif;
                text-align: center;
                color: #1f2937;
            }

            .container {
                border: 8px solid #0b3c5d;
                padding: 20px;
            }

            .inner {
                border: 2px solid #d4af37;
                padding: 20px;
            }

            h1 {
                font-size: 28px;
                color: #0b3c5d;
                margin: 10px 0;
            }

            .subtitle {
                font-size: 14px;
                color: #666;
            }

            .name {
                font-size: 30px;
                font-weight: bold;
                margin: 15px 0;
                color: #0b3c5d;
                border-bottom: 2px solid #d4af37;
                display: inline-block;
            }

            .formation {
                font-size: 18px;
                font-weight: bold;
                margin: 10px 0;
                color: #0b3c5d;
            }

            .row {
                width: 100%;
                margin-top: 40px;
            }

            .col {
                display: inline-block;
                width: 30%;
                font-size: 12px;
                vertical-align: top;
            }

            .qr {
                margin-top: 30px;
            }

            .qr img {
                width: 80px;
                height: 80px;
            }
        </style>

        <div class='container'>
            <div class='inner'>
                <h1>CERTIFICATE</h1>
                <div class='subtitle'>OF ACHIEVEMENT</div>

                <p class='subtitle'>This certificate is proudly presented to</p>

                <div class='name'>{$safeUserName}</div>

                <p class='subtitle'>for successfully completing</p>

                <div class='formation'>{$safeFormationTitle}</div>

                <div class='row'>
                    <div class='col'>
                        <strong>Date</strong><br>
                        {$date}
                    </div>

                    <div class='col'>
                        ______________________<br>
                        Instructor
                    </div>

                    <div class='col'>
                        <strong>ID</strong><br>
                        {$safeCertificateId}
                    </div>
                </div>

                " . ($qrBase64 !== '' ? "
                <div class='qr'>
                    <img src='data:image/png;base64,{$qrBase64}' alt='QR Code' />
                </div>
                " : "") . "
            </div>
        </div>
        ";

        error_log('[CertificateService] Rendering PDF...');
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();
        error_log('[CertificateService] PDF rendered successfully');

        $dir = $this->projectDir . '/public/uploads/certificates';
        error_log('[CertificateService] Certificate directory: ' . $dir);

        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException('Impossible de créer le dossier des certificats: ' . $dir);
        }

        if (!is_writable($dir)) {
            throw new \RuntimeException('Le dossier des certificats n’est pas accessible en écriture: ' . $dir);
        }

        $fileName = sprintf(
            'cert_%s_%s_%s.pdf',
            (string) $user->getId(),
            (string) $formation->getId(),
            uniqid('', true)
        );

        $fullPath = $dir . DIRECTORY_SEPARATOR . $fileName;
        error_log('[CertificateService] Certificate file path: ' . $fullPath);

        $pdfContent = $dompdf->output();
        error_log('[CertificateService] PDF content size: ' . strlen($pdfContent) . ' bytes');

        $written = file_put_contents($fullPath, $pdfContent);
        if ($written === false) {
            throw new \RuntimeException('Impossible d’écrire le certificat PDF: ' . $fullPath);
        }

        clearstatcache(true, $fullPath);

        if (!is_file($fullPath)) {
            throw new \RuntimeException('Le certificat PDF n’existe pas après écriture: ' . $fullPath);
        }

        $fileSize = filesize($fullPath);
        if ($fileSize === false || $fileSize === 0) {
            throw new \RuntimeException('Le certificat PDF est vide: ' . $fullPath);
        }

        error_log('[CertificateService] PDF written successfully: ' . $fileSize . ' bytes');
        error_log('[CertificateService] Final certificate path: ' . $fullPath);

        return $fullPath;
    }
}
