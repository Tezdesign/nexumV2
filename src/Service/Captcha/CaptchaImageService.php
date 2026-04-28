<?php

namespace App\Service\Captcha;

/**
 * Renders a small PNG captcha using GD (letters + digits, readable).
 */
final class CaptchaImageService
{
    private const CHARSET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public function generateCode(int $length = 5): string
    {
        $out = '';
        $max = \strlen(self::CHARSET) - 1;
        for ($i = 0; $i < $length; ++$i) {
            $out .= self::CHARSET[random_int(0, $max)];
        }

        return $out;
    }

    public function renderPng(string $code): string
    {
        if (!extension_loaded('gd')) {
            throw new \RuntimeException('PHP GD extension is required for captcha images.');
        }

        $code = preg_replace('/[^A-Z0-9]/i', '', $code) ?? '';
        if ($code === '') {
            throw new \InvalidArgumentException('Captcha code must be non-empty alphanumeric.');
        }

        $width = 200;
        $height = 56;
        $im = imagecreatetruecolor($width, $height);
        if ($im === false) {
            throw new \RuntimeException('Could not create captcha image.');
        }

        $bg = imagecolorallocate($im, random_int(245, 255), random_int(245, 255), random_int(245, 255));
        if ($bg === false) {
            throw new \RuntimeException('Could not allocate captcha background color.');
        }
        imagefill($im, 0, 0, $bg);

        for ($i = 0; $i < 400; ++$i) {
            $pixelColor = imagecolorallocate($im, random_int(200, 235), random_int(200, 235), random_int(200, 235));
            if ($pixelColor === false) {
                continue;
            }
            imagesetpixel(
                $im,
                random_int(0, $width - 1),
                random_int(0, $height - 1),
                $pixelColor
            );
        }

        for ($i = 0; $i < 5; ++$i) {
            $lineColor = imagecolorallocate($im, random_int(180, 220), random_int(180, 220), random_int(180, 220));
            if ($lineColor === false) {
                continue;
            }
            imageline(
                $im,
                random_int(0, $width),
                random_int(0, $height),
                random_int(0, $width),
                random_int(0, $height),
                $lineColor
            );
        }

        $chars = str_split(strtoupper($code));
        $x = 14;
        foreach ($chars as $ch) {
            $col = imagecolorallocate($im, random_int(20, 90), random_int(20, 90), random_int(20, 90));
            if ($col === false) {
                continue;
            }
            $y = random_int(18, 26);
            imagestring($im, 5, $x, $y, $ch, $col);
            $x += random_int(26, 32);
        }

        ob_start();
        imagepng($im);
        imagedestroy($im);

        $binary = ob_get_clean();

        return \is_string($binary) ? $binary : '';
    }
}
