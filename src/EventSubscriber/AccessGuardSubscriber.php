<?php

namespace App\EventSubscriber;

use App\Attribute\RequireAdmin;
use App\Attribute\RequireLogin;
use App\Service\AuthService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class AccessGuardSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::CONTROLLER => 'onController'];
    }

    public function onController(ControllerEvent $event): void
    {
        $attributes = $event->getAttributes();
        $needsAdmin = ($attributes[RequireAdmin::class] ?? []) !== [];
        if (!$event->isMainRequest() || (!$needsAdmin && ($attributes[RequireLogin::class] ?? []) === [])) {
            return;
        }

        if (!$this->authService->isLoggedIn()) {
            // A page navigation goes to the welcome page; fetch and Ajax calls (which do not ask for HTML) get a 401.
            $request = $event->getRequest();
            $isAjax = $request->isXmlHttpRequest() || !str_contains((string) $request->headers->get('Accept', ''), 'text/html');
            $url = $this->urlGenerator->generate('welcome');
            $event->setController(static fn (): Response => $isAjax
                ? new Response('Authentication required.', Response::HTTP_UNAUTHORIZED)
                : new RedirectResponse($url));

            return;
        }

        if ($needsAdmin && !$this->authService->isAdmin()) {
            throw new AccessDeniedHttpException('Administrator access required.');
        }
    }
}
