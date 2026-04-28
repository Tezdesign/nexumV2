<?php

namespace App\Service;

use Dompdf\Dompdf;
use App\Entity\UserHandling\Utilisateur;
use App\Entity\Formation;

class CertificateService
{
    public function generate(Utilisateur $user, Formation $formation, ?string $qrPath = null): string
    {
        $dompdf = new Dompdf();

        // 🔥 OPTIONS IMPORTANTES
        $dompdf->set_option('isRemoteEnabled', true);
        $dompdf->set_option('isHtml5ParserEnabled', true);

        $date = (new \DateTime())->format('d M Y');
        $certificateId = 'CERT-' . strtoupper(substr(uniqid(), -8));

        // 🔥 QR → BASE64 (FIABLE DOMPDF)
        $qrBase64 = '';

        if ($qrPath) {
            $fullPath = __DIR__ . '/../../public/' . $qrPath;

            if (file_exists($fullPath)) {
                $content = @file_get_contents($fullPath);
                if ($content !== false) {
                    $qrBase64 = base64_encode($content);
                }
            }
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
            }
        
            .qr {
                margin-top: 30px;
            }
        
            .qr img {
                width: 80px;
            }
        
        </style>
        
        <div class='container'>
            <div class='inner'>
        
                <h1>CERTIFICATE</h1>
                <div class='subtitle'>OF ACHIEVEMENT</div>
        
                <p class='subtitle'>This certificate is proudly presented to</p>
        
                <div class='name'>{$user->getNom()}</div>
        
                <p class='subtitle'>for successfully completing</p>
        
                <div class='formation'>{$formation->getTitre()}</div>
        
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
                        {$certificateId}
                    </div>
                </div>
        
                ".($qrBase64 ? "
                <div class='qr'>
                    <img src='data:image/png;base64,{$qrBase64}' />
                </div>
                " : "")."
        
            </div>
        </div>
        ";

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        // 📁 SAVE
        $dir = __DIR__ . '/../../public/uploads/certificates/';

        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $fileName = uniqid() . '.pdf';
        $fullPath = $dir . $fileName;

        file_put_contents($fullPath, $dompdf->output());

        return $fullPath;
    }
}