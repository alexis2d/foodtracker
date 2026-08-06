<?php

namespace App\EventSubscriber;

use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * Moves the JWT issued on login/register out of the JSON body and into an
 * httpOnly cookie, so the frontend never has to touch the raw token.
 */
final class JwtCookieSubscriber implements EventSubscriberInterface
{
    public const COOKIE_NAME = 'BEARER';

    public function __construct(
        private readonly string $environment,
        private readonly int $tokenTtl,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            Events::AUTHENTICATION_SUCCESS => 'onAuthenticationSuccess',
        ];
    }

    public function onAuthenticationSuccess(AuthenticationSuccessEvent $event): void
    {
        $data = $event->getData();
        $token = $data['token'] ?? null;

        if (null === $token) {
            return;
        }

        unset($data['token']);
        $event->setData($data);

        $cookie = Cookie::create(self::COOKIE_NAME)
            ->withValue($token)
            ->withHttpOnly(true)
            ->withSecure('dev' !== $this->environment)
            ->withSameSite(Cookie::SAMESITE_LAX)
            ->withPath('/')
            ->withExpires(time() + $this->tokenTtl);

        $event->getResponse()->headers->setCookie($cookie);
    }
}
