<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Blocks login for unverified accounts. Checked post-auth (after the
 * password has already been verified) so that a wrong password always
 * fails with the generic "bad credentials" message — checking pre-auth
 * would leak "this account exists but isn't verified" to anyone who
 * merely knows the email address, without needing the password.
 */
final class AuthUserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
    }

    public function checkPostAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }

        if (!$user->isVerified()) {
            throw new CustomUserMessageAccountStatusException(
                'Veuillez activer votre compte via le lien reçu par email avant de vous connecter.'
            );
        }
    }
}
