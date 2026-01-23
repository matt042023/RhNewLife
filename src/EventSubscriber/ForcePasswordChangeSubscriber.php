<?php

namespace App\EventSubscriber;

use App\Entity\User;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Subscriber that redirects users who need to change their password
 * to the password change page before allowing access to other routes.
 */
class ForcePasswordChangeSubscriber implements EventSubscriberInterface
{
    private const ALLOWED_ROUTES = [
        'app_force_password_change',
        'app_logout',
        '_wdt',
        '_profiler',
    ];

    public function __construct(
        private readonly TokenStorageInterface $tokenStorage,
        private readonly UrlGeneratorInterface $urlGenerator
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 10],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $token = $this->tokenStorage->getToken();
        if ($token === null) {
            return;
        }

        $user = $token->getUser();
        if (!$user instanceof User) {
            return;
        }

        // Check if user needs to change password
        if (!$user->isForcePasswordChange()) {
            return;
        }

        $request = $event->getRequest();
        $route = $request->attributes->get('_route');

        // Allow access to certain routes
        if ($this->isRouteAllowed($route)) {
            return;
        }

        // Redirect to password change page
        $response = new RedirectResponse(
            $this->urlGenerator->generate('app_force_password_change')
        );

        $event->setResponse($response);
    }

    private function isRouteAllowed(?string $route): bool
    {
        if ($route === null) {
            return true;
        }

        foreach (self::ALLOWED_ROUTES as $allowedRoute) {
            if (str_starts_with($route, $allowedRoute)) {
                return true;
            }
        }

        return false;
    }
}
