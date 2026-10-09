<?php

namespace App\Tests\Controller;

use App\Controller\chat\ConversationController;
use App\Repository\Chat\ConversationParticipantRepository;
use App\Service\AuthService;
use App\Service\Chat\UserAvatarUrl;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Psr\Log\NullLogger;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\Request;

final class ChatCallTokenTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $sentToTokenServer = [];

    private function controller(int $userId, bool $isParticipant): ConversationController
    {
        $_ENV['CHAT_CALL_TOKEN_PROXY_TARGET'] = 'http://token.test/livekit/token';

        $auth = $this->createMock(AuthService::class);
        $auth->method('getCurrentUserId')->willReturn($userId);
        $auth->method('getCurrentUser')->willReturn(['prenom' => 'Ada', 'nom' => 'Lovelace']);

        $http = new MockHttpClient(function (string $method, string $url, array $options): MockResponse {
            $this->sentToTokenServer = ['url' => $url, 'query' => $options['query'] ?? []];

            return new MockResponse(json_encode(['token' => 'jwt-token']));
        });

        $controller = new ConversationController($auth, $http, new NullLogger(), new UserAvatarUrl($this->createMock(UrlGeneratorInterface::class)));
        $controller->setContainer(new Container());

        return $controller;
    }

    private function participants(bool $isParticipant): ConversationParticipantRepository
    {
        $repository = $this->createMock(ConversationParticipantRepository::class);
        $repository->method('isActiveParticipant')->willReturn($isParticipant);

        return $repository;
    }

    public function testTokenIsIssuedForTheCallersOwnIdentityAndIgnoresTheIdentityTheBrowserSends(): void
    {
        $request = Request::create('/apps-chat/livekit/token', 'GET', ['room' => 'conv-12', 'identity' => 'user-3', 'name' => 'Mallory']);

        $response = $this->controller(7, true)->proxyLivekitToken($request, $this->participants(true));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['room' => 'conv-12', 'identity' => 'user-7', 'name' => 'Ada Lovelace'], $this->sentToTokenServer['query']);
    }

    public function testNonMemberGetsNoToken(): void
    {
        $request = Request::create('/apps-chat/livekit/token', 'GET', ['room' => 'conv-12']);

        $response = $this->controller(7, false)->proxyLivekitToken($request, $this->participants(false));

        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame([], $this->sentToTokenServer, 'the token server must not be called');
    }

    /** @dataProvider badRooms */
    public function testOnlyConversationCallRoomsAreAccepted(string $room): void
    {
        $response = $this->controller(7, true)->proxyLivekitToken(Request::create('/x', 'GET', ['room' => $room]), $this->participants(true));

        $this->assertSame(400, $response->getStatusCode());
    }

    /** @return iterable<string, array{string}> */
    public static function badRooms(): iterable
    {
        yield 'empty' => [''];
        yield 'other format' => ['lobby'];
        yield 'underscore' => ['conv_12'];
        yield 'zero' => ['conv-0'];
        yield 'trailing text' => ['conv-12-admin'];
    }

    public function testCallTokenForAConversationAlwaysUsesThatConversationsRoom(): void
    {
        $response = $this->controller(7, true)->getCallToken(5, $this->participants(true));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('conv-5', $this->sentToTokenServer['query']['room']);
        $this->assertSame('user-7', $this->sentToTokenServer['query']['identity']);
    }

    public function testUpstreamFailureDoesNotLeakTheInternalAddress(): void
    {
        $_ENV['CHAT_CALL_TOKEN_PROXY_TARGET'] = 'http://10.1.2.3:8090/livekit/token';
        $auth = $this->createMock(AuthService::class);
        $auth->method('getCurrentUserId')->willReturn(7);
        $http = new MockHttpClient(static fn (): MockResponse => new MockResponse('', ['error' => 'Connection refused for http://10.1.2.3:8090']));
        $controller = new ConversationController($auth, $http, new NullLogger(), new UserAvatarUrl($this->createMock(UrlGeneratorInterface::class)));
        $controller->setContainer(new Container());

        $response = $controller->getCallToken(5, $this->participants(true));

        $this->assertSame(502, $response->getStatusCode());
        $this->assertStringNotContainsString('10.1.2.3', (string) $response->getContent());
    }
}
