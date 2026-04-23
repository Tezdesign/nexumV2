<?php

namespace App\Service;

use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Email;

class AdminMailService
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly string $mailFrom = 'noreply@nexum.local',
        private readonly ?string $mailDsn = null,
    ) {
    }

    public function sendStatusChangeEmail(string $toEmail, string $newStatus): void
    {
        $isActive = \in_array(strtolower(trim($newStatus)), ['actif', 'active'], true);

        $content = $isActive
            ? "Bonjour,\n\nVotre compte a ete active. Vous pouvez desormais vous connecter.\n\nCordialement,\nL'equipe Nexum."
            : "Bonjour,\n\nVotre compte a ete suspendu. Pour plus d'informations, veuillez contacter l'administrateur.\n\nCordialement,\nL'equipe Nexum.";

        $email = (new Email())
            ->from($this->mailFrom)
            ->to($toEmail)
            ->subject('Changement de statut de votre compte')
            ->text($content);

        $this->resolveMailer()->send($email);
    }

    public function sendReclamationStatusEmail(string $toEmail, string $titre, ?string $projet, string $newStatus): void
    {
        $safeProjet = trim((string) $projet) !== '' ? $projet : 'Non specifie';
        $content = "Bonjour,\n\nLe statut de votre reclamation \"{$titre}\" (projet : {$safeProjet}) a ete mis a jour : {$newStatus}.\n\nCordialement,\nL'equipe de support Nexum.";

        $email = (new Email())
            ->from($this->mailFrom)
            ->to($toEmail)
            ->subject('Mise a jour de votre reclamation')
            ->text($content);

        $this->resolveMailer()->send($email);
    }

    public function sendPasswordResetCode(string $toEmail, string $code): void
    {
        $content = "Bonjour,\n\nVous avez demande la reinitialisation de votre mot de passe.\nVoici votre code de verification : {$code}\n\nCe code est valable 10 minutes.\n\nSi vous n'etes pas a l'origine de cette demande, ignorez cet email.\n\nCordialement,\nL'equipe Nexum.";

        $email = (new Email())
            ->from($this->mailFrom)
            ->to($toEmail)
            ->subject('Code de reinitialisation de mot de passe')
            ->text($content);

        $this->resolveMailer()->send($email);
    }

    public function sendInvitationEmail(string $toEmail, string $firstName, string $password): void
    {
        $content = "Bonjour {$firstName},\n\nVotre compte sur la plateforme Nexum a ete cree par un administrateur.\nVoici vos identifiants de connexion :\nEmail : {$toEmail}\nMot de passe temporaire : {$password}\n\nNous vous recommandons de changer votre mot de passe apres votre premiere connexion.\n\nCordialement,\nL'equipe Nexum.";

        $email = (new Email())
            ->from($this->mailFrom)
            ->to($toEmail)
            ->subject('Bienvenue sur Nexum - Votre compte a ete cree')
            ->text($content);

        $this->resolveMailer()->send($email);
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
