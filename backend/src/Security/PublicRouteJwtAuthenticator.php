<?php

namespace App\Security;

use Lexik\Bundle\JWTAuthenticationBundle\Security\Authenticator\JWTAuthenticator;
use Symfony\Component\HttpFoundation\Request;

/**
 * The base JWTAuthenticator authenticates (and hard-fails on invalid/expired
 * tokens) on *any* request carrying a BEARER cookie, regardless of the
 * access_control rules for the route. That breaks public endpoints like
 * /api/register and /api/login: a stale cookie from a previous session
 * makes them return 401 before the request ever reaches the controller.
 *
 * Skipping authentication entirely on these paths lets a stale cookie be
 * ignored there, so the PUBLIC_ACCESS rule in security.yaml actually applies.
 */
final class PublicRouteJwtAuthenticator extends JWTAuthenticator
{
    private const PUBLIC_PATHS = [
        '/api/register',
        '/api/login',
        '/api/verify-email',
        '/api/resend-verification',
        '/api/forgot-password',
        '/api/reset-password',
        '/api/confirm-password-change',
    ];

    public function supports(Request $request): ?bool
    {
        if (in_array($request->getPathInfo(), self::PUBLIC_PATHS, true)) {
            return false;
        }

        return parent::supports($request);
    }
}
