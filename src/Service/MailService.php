<?php

namespace App\Service;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class MailService
{
    public function sendCertificate($user, $formation, string $filePath, MailerInterface $mailer)
    {
        if (!file_exists($filePath)) {
            throw new \Exception('Fichier certificat introuvable');
        }

        $email = (new Email())
            ->from('mariem@longevityplus.store')
            ->to($user->getEmail())
            ->subject('🎓 Votre certificat - '.$formation->getTitre())
            ->html("
                <h2>Félicitations {$user->getNom()} 👏</h2>
                <p>Vous avez réussi la formation :</p>
                <h3>{$formation->getTitre()}</h3>
                <p>Votre certificat est en pièce jointe 📎</p>
                <br>
                <small>NEXUM Academy</small>
            ")
            ->attachFromPath($filePath, 'certificat.pdf'); // 🔥 rename clean

        $mailer->send($email);
    }
}