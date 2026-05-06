<?php

namespace App\Service;

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class MailService
{
    public function sendCertificate($user, $formation, string $filePath, MailerInterface $mailer): void
    {
        error_log('[MailService] ===== sendCertificate entered =====');

        $to = trim((string) $user->getEmail());
        $userName = trim((string) $user->getNom());
        $formationTitle = trim((string) $formation->getTitre());

        error_log('[MailService] Recipient: ' . $to);
        error_log('[MailService] User: ' . $userName);
        error_log('[MailService] Formation: ' . $formationTitle);
        error_log('[MailService] Certificate file path: ' . $filePath);
        error_log('[MailService] Mailer class: ' . get_debug_type($mailer));

        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('Email destinataire invalide: ' . $to);
        }

        if (!is_file($filePath)) {
            throw new \RuntimeException('Fichier certificat introuvable: ' . $filePath);
        }

        if (!is_readable($filePath)) {
            throw new \RuntimeException('Fichier certificat non lisible: ' . $filePath);
        }

        $size = filesize($filePath);
        if ($size === false || $size === 0) {
            throw new \RuntimeException('Fichier certificat vide: ' . $filePath);
        }

        error_log('[MailService] Certificate file exists, size: ' . $size . ' bytes');

        $email = (new Email())
            ->from('mariem@longevityplus.store')
            ->to($to)
            ->subject('🎓 Votre certificat - ' . $formationTitle)
            ->html("
                <h2>Félicitations {$userName} 👏</h2>
                <p>Vous avez réussi la formation :</p>
                <h3>{$formationTitle}</h3>
                <p>Votre certificat est en pièce jointe 📎</p>
                <br>
                <small>NEXUM Academy</small>
            ")
            ->attachFromPath($filePath, 'certificat.pdf');

        try {
            error_log('[MailService] Sending email...');
            $mailer->send($email);
            error_log('[MailService] Email sent successfully to: ' . $to);
        } catch (\Throwable $e) {
            error_log('[MailService] ERROR: Email sending failed: ' . $e->getMessage());
            error_log('[MailService] Exception type: ' . get_class($e));
            error_log('[MailService] Exception trace: ' . $e->getTraceAsString());

            throw new \RuntimeException('Impossible d’envoyer le certificat: ' . $e->getMessage(), 0, $e);
        }
    }
}