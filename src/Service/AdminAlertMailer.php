<?php

namespace App\Service;

use App\Entity\UserHandling\Utilisateur;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Tells the administrator by email that a new account waits for activation.
 *
 * The mail is only queued while the request runs and is sent after the response has gone out, so a slow or
 * unreachable mail service can never hold up or break the sign up. Empty sender or recipient means "do nothing".
 */
final class AdminAlertMailer
{
    /** @var list<Email> */
    private array $queue = [];

    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly LoggerInterface $logger,
        private readonly string $from = '',
        private readonly string $to = '',
    ) {
    }

    public function notifyNewUser(Utilisateur $user): void
    {
        if ($this->from === '' || $this->to === '') {
            return;
        }

        $this->queue[] = (new Email())
            ->from($this->from)
            ->to($this->to)
            ->subject('Nexum: new account waiting for activation')
            ->text(implode("\n", [
                'A new account was created and cannot log in until you activate it.',
                '',
                'Name: ' . trim($user->getPrenom() . ' ' . $user->getNom()),
                'Email: ' . $user->getEmail(),
                'Role: ' . $user->getRole(),
                '',
                'Activate it here: ' . $this->urlGenerator->generate('admin_users_index', [], UrlGeneratorInterface::ABSOLUTE_URL),
            ]));
    }

    #[AsEventListener(event: KernelEvents::TERMINATE)]
    public function sendQueued(): void
    {
        $emails = $this->queue;
        $this->queue = [];

        foreach ($emails as $email) {
            try {
                $this->mailer->send($email);
            } catch (\Throwable $e) {
                $this->logger->error('Admin alert email failed', ['exception' => $e]);
            }
        }
    }
}
