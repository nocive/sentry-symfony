<?php

namespace Sentry\SentryBundle\EventListener;

use Sentry\State\Hub;
use Sentry\State\HubInterface;
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Class RequestListener
 * @package Sentry\SentryBundle\EventListener
 */
final class RequestListener
{
    /** @var HubInterface */
    private $hub;

    /** @var TokenStorageInterface|null */
    private $tokenStorage;

    /** @var AuthorizationCheckerInterface|null */
    private $authorizationChecker;

    /**
     * RequestListener constructor.
     * @param HubInterface $hub
     * @param TokenStorageInterface|null $tokenStorage
     * @param AuthorizationCheckerInterface|null $authorizationChecker
     */
    public function __construct(
        HubInterface $hub,
        ?TokenStorageInterface $tokenStorage,
        ?AuthorizationCheckerInterface $authorizationChecker
    ) {
        $this->hub = $hub; // not used, needed to trigger instantiation
        $this->tokenStorage = $tokenStorage;
        $this->authorizationChecker = $authorizationChecker;
    }

    /**
     * Set the username from the security context by listening on core.request
     *
     * @param GetResponseEvent $event
     */
    public function onKernelRequest(GetResponseEvent $event): void
    {
        if (! $event->isMasterRequest()) {
            return;
        }

        if (null === $this->tokenStorage || null === $this->authorizationChecker) {
            return;
        }

        $token = $this->tokenStorage->getToken();

        if (
            null !== $token
            && $token->isAuthenticated()
            && $this->authorizationChecker->isGranted(AuthenticatedVoter::IS_AUTHENTICATED_REMEMBERED)
        ) {
            $userData = $this->getUserData($token->getUser());
        }

        $userData['ip_address'] = $event->getRequest()->getClientIp();

        Hub::getCurrent()
            ->getScope()
            ->setUser($userData);
    }

    public function onKernelController(FilterControllerEvent $event): void
    {
        if (! $event->isMasterRequest()) {
            return;
        }

        $matchedRoute = $event->getRequest()->attributes->get('_route');

        Hub::getCurrent()
            ->getScope()
            ->setTag('route', $matchedRoute);
    }

    /**
     * @param UserInterface | object | string $user
     */
    private function getUserData($user): array
    {
        if ($user instanceof UserInterface) {
            return [
                'username' => $user->getUsername(),
            ];
        }

        if (is_string($user)) {
            return [
                'username' => $user,
            ];
        }

        if (is_object($user) && method_exists($user, '__toString')) {
            return [
                'username' => $user->__toString(),
            ];
        }

        return [];
    }
}