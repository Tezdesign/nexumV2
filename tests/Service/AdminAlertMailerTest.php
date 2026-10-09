<?php

namespace App\Tests\Service;

use App\Entity\UserHandling\Utilisateur;
use App\Service\AdminAlertMailer;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\RawMessage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class AdminAlertMailerTest extends TestCase
{
    /** @var list<RawMessage> */
    private array $sent = [];

    private function mailer(string $from, string $to, bool $fails = false): AdminAlertMailer
    {
        $transport = $this->createMock(MailerInterface::class);
        $transport->method('send')->willReturnCallback(function (RawMessage $message) use ($fails): void {
            if ($fails) {
                throw new TransportException('Mailjet is down');
            }
            $this->sent[] = $message;
        });
        $urls = $this->createMock(UrlGeneratorInterface::class);
        $urls->method('generate')->willReturn('https://nexum.test/admin/users');

        return new AdminAlertMailer($transport, $urls, new NullLogger(), $from, $to);
    }

    private function user(): Utilisateur
    {
        return (new Utilisateur())->setPrenom('Ann')->setNom('Lee')->setEmail('ann@nexum.test')->setRole('employee');
    }

    public function testNothingIsSentDuringTheRequestOnlyAfterIt(): void
    {
        $alert = $this->mailer('from@nexum.test', 'admin@nexum.test');
        $alert->notifyNewUser($this->user());
        $this->assertCount(0, $this->sent);

        $alert->sendQueued();
        $this->assertCount(1, $this->sent);
        $mail = $this->sent[0];
        $this->assertSame('admin@nexum.test', $mail->getTo()[0]->getAddress());
        $this->assertStringContainsString('ann@nexum.test', $mail->getTextBody());
        $this->assertStringContainsString('https://nexum.test/admin/users', $mail->getTextBody());
    }

    public function testEachAlertIsSentOnce(): void
    {
        $alert = $this->mailer('from@nexum.test', 'admin@nexum.test');
        $alert->notifyNewUser($this->user());
        $alert->sendQueued();
        $alert->sendQueued();

        $this->assertCount(1, $this->sent);
    }

    public function testNoAddressesMeansNoEmail(): void
    {
        foreach ([['', 'admin@nexum.test'], ['from@nexum.test', ''], ['', '']] as [$from, $to]) {
            $alert = $this->mailer($from, $to);
            $alert->notifyNewUser($this->user());
            $alert->sendQueued();
        }

        $this->assertCount(0, $this->sent);
    }

    public function testAMailServiceFailureNeverEscapes(): void
    {
        $alert = $this->mailer('from@nexum.test', 'admin@nexum.test', fails: true);
        $alert->notifyNewUser($this->user());
        $alert->sendQueued();

        $this->addToAssertionCount(1);
    }
}
