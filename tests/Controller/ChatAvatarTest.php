<?php

namespace App\Tests\Controller;

use App\Controller\chat\ConversationController;
use App\Entity\UserHandling\Utilisateur;
use App\Repository\UserHandling\UtilisateurRepository;
use App\Service\AuthService;
use App\Service\Chat\UserAvatarUrl;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class ChatAvatarTest extends TestCase
{
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    private function user(?string $image, int $id = 7): Utilisateur
    {
        $user = $this->createMock(Utilisateur::class);
        $user->method('getId')->willReturn($id);
        $user->method('getImagelink')->willReturn($image);

        return $user;
    }

    private function controller(?Utilisateur $user): ConversationController
    {
        $users = $this->createMock(UtilisateurRepository::class);
        $users->method('find')->willReturn($user);
        $this->users = $users;

        $controller = new ConversationController(
            $this->createMock(AuthService::class),
            new MockHttpClient(),
            new NullLogger(),
            new UserAvatarUrl($this->createMock(UrlGeneratorInterface::class)),
        );
        $controller->setContainer(new Container());

        return $controller;
    }

    private UtilisateurRepository $users;

    public function testMessagesCarryAShortUrlNotTheImage(): void
    {
        $urls = $this->createMock(UrlGeneratorInterface::class);
        $urls->method('generate')->with('apps-chat-user-avatar', ['userId' => 7])->willReturn('/apps-chat/users/7/avatar');

        $this->assertSame('/apps-chat/users/7/avatar', (new UserAvatarUrl($urls))->for($this->user('some-bytes')));
    }

    public function testUsersWithoutAPictureGetNoUrlSoThePageShowsInitials(): void
    {
        $generator = new UserAvatarUrl($this->createMock(UrlGeneratorInterface::class));

        $this->assertNull($generator->for(null));
        $this->assertNull($generator->for($this->user(null)));
        $this->assertNull($generator->for($this->user('')));
    }

    public function testAvatarEndpointServesARealPictureWithCacheHeaders(): void
    {
        $png = (string) base64_decode(self::PNG);
        $controller = $this->controller($this->user($png));

        $response = $controller->userAvatar(7, Request::create('/apps-chat/users/7/avatar'), $this->users);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('image/png', $response->headers->get('Content-Type'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));
        $this->assertSame('"'.md5($png).'"', $response->headers->get('ETag'));
    }

    public function testAvatarEndpointAnswers304WhenThePictureDidNotChange(): void
    {
        $png = (string) base64_decode(self::PNG);
        $request = Request::create('/apps-chat/users/7/avatar');
        $request->headers->set('If-None-Match', '"'.md5($png).'"');

        $response = $this->controller($this->user($png))->userAvatar(7, $request, $this->users);

        $this->assertSame(304, $response->getStatusCode());
    }

    /** @dataProvider notPictures */
    public function testAvatarEndpointNeverServesSvgHtmlOrUnknownUsers(?string $bytes): void
    {
        $response = $this->controller($bytes === null ? null : $this->user($bytes))->userAvatar(7, Request::create('/x'), $this->users);

        $this->assertSame(404, $response->getStatusCode());
    }

    /** @return iterable<string, array{?string}> */
    public static function notPictures(): iterable
    {
        yield 'svg with script' => ['<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'];
        yield 'html' => ['<html><script>alert(1)</script></html>'];
        yield 'a legacy url stored in the column' => ['https://cdn.example/a.png'];
        yield 'empty' => [''];
        yield 'unknown user' => [null];
    }
}
