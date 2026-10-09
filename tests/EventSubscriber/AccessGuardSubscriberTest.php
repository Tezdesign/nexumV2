<?php

namespace App\Tests\EventSubscriber;

use App\Attribute\RequireAdmin;
use App\Attribute\RequireLogin;
use App\EventSubscriber\AccessGuardSubscriber;
use App\Service\AuthService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class AccessGuardSubscriberTest extends TestCase
{
    private function event(bool $guarded, bool $ajax = false, string $attribute = RequireAdmin::class, string $accept = 'text/html'): ControllerEvent
    {
        $request = Request::create('/admin/resources/');
        $request->headers->set('Accept', $accept);
        if ($ajax) {
            $request->headers->set('X-Requested-With', 'XMLHttpRequest');
        }

        $event = new ControllerEvent($this->createMock(HttpKernelInterface::class), static fn (): Response => new Response('ok'), $request, HttpKernelInterface::MAIN_REQUEST);
        $event->setController(static fn (): Response => new Response('ok'), $guarded ? [$attribute => [new $attribute()]] : []);

        return $event;
    }

    private function subscriber(bool $loggedIn, bool $admin): AccessGuardSubscriber
    {
        $auth = $this->createMock(AuthService::class);
        $auth->method('isLoggedIn')->willReturn($loggedIn);
        $auth->method('isAdmin')->willReturn($admin);
        $urls = $this->createMock(UrlGeneratorInterface::class);
        $urls->method('generate')->willReturn('/welcome');

        return new AccessGuardSubscriber($auth, $urls);
    }

    public function testVisitorIsSentToTheWelcomePage(): void
    {
        $event = $this->event(true);
        $this->subscriber(false, false)->onController($event);

        $response = ($event->getController())();
        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('/welcome', $response->getTargetUrl());
    }

    public function testVisitorAjaxCallGetsA401InsteadOfARedirect(): void
    {
        $event = $this->event(true, true);
        $this->subscriber(false, false)->onController($event);

        $this->assertSame(401, ($event->getController())()->getStatusCode());
    }

    public function testLoggedInNonAdminIsForbidden(): void
    {
        $this->expectException(AccessDeniedHttpException::class);
        $this->subscriber(true, false)->onController($this->event(true));
    }

    public function testAdminPassesThrough(): void
    {
        $event = $this->event(true);
        $this->subscriber(true, true)->onController($event);

        $this->assertSame('ok', ($event->getController())()->getContent());
    }

    public function testUnmarkedControllersAreNotTouched(): void
    {
        $event = $this->event(false);
        $this->subscriber(false, false)->onController($event);

        $this->assertSame('ok', ($event->getController())()->getContent());
    }

    public function testFetchWithoutHtmlAcceptGetsA401NotARedirect(): void
    {
        $event = $this->event(true, false, RequireLogin::class, '*/*');
        $this->subscriber(false, false)->onController($event);

        $this->assertSame(401, ($event->getController())()->getStatusCode());
    }

    public function testRequireLoginLetsAnyLoggedInUserThrough(): void
    {
        $event = $this->event(true, false, RequireLogin::class);
        $this->subscriber(true, false)->onController($event);

        $this->assertSame('ok', ($event->getController())()->getContent());
    }

    public function testRequireLoginSendsAVisitorToTheWelcomePage(): void
    {
        $event = $this->event(true, false, RequireLogin::class);
        $this->subscriber(false, false)->onController($event);

        $this->assertInstanceOf(RedirectResponse::class, ($event->getController())());
    }
}
