<?php
namespace App\Service;

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

class QrService
{
    public function generate(string $data): string
    {
        $qrCode = new QrCode($data);
 
        $writer = new PngWriter();
        $result = $writer->write($qrCode);

        $path = 'uploads/qrcode/'.uniqid().'.png';
        file_put_contents($path, $result->getString());

        return $path;
    }
}