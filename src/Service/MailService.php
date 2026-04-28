<?php

namespace App\Service;

use App\Entity\Formation;
use App\Entity\UserHandling\Utilisateur;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Email;

class MailService
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly string $mailFrom = 'noreply@nexum.local',
        private readonly ?string $mailDsn = null,
    ) {
    }

    public function sendCertificate(Utilisateur $user, Formation $formation, string $filePath, ?MailerInterface $mailer = null): void
    {
        if (!file_exists($filePath)) {
            throw new \Exception('Fichier certificat introuvable');
        }

        $email = (new Email())
            ->from($this->mailFrom)
            ->to((string) $user->getEmail())
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

        ($mailer ?? $this->resolveMailer())->send($email);
    }

    private function resolveMailer(): MailerInterface
    {
        $dsn = trim((string) $this->mailDsn);
        if ($dsn === '') {
            return $this->mailer;
        }

        return new Mailer(Transport::fromDsn($dsn));
    }
}
