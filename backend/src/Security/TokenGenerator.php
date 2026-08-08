<?php

namespace App\Security;

/**
 * Generates single-use tokens for the account-verification / password-reset /
 * password-change-confirmation flows. Only the hash is ever persisted — the
 * raw token exists just long enough to be embedded in the emailed link.
 */
final class TokenGenerator
{
    public function generate(): string
    {
        return bin2hex(random_bytes(32));
    }

    public function hash(string $rawToken): string
    {
        return hash('sha256', $rawToken);
    }
}
