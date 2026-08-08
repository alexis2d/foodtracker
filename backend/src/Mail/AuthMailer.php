<?php

namespace App\Mail;

use App\Entity\User;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

/**
 * Builds and sends the account-lifecycle emails (activation, password reset,
 * password-change confirmation). Plain text bodies only — this is an API-only
 * backend with no templating engine installed.
 *
 * Send failures are logged, not thrown: a mail outage shouldn't turn a
 * register/forgot-password/change-password request into a 500, since the
 * user can always retry (resend-verification, forgot-password again, etc).
 */
final class AuthMailer
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly LoggerInterface $logger,
        #[Autowire(env: 'MAILER_FROM')] private readonly string $fromAddress,
        #[Autowire(env: 'FRONTEND_URL')] private readonly string $frontendUrl,
    ) {
    }

    public function sendVerificationEmail(User $user, string $rawToken): void
    {
        $link = sprintf('%s/verify-email?token=%s', $this->frontendUrl, $rawToken);

        $this->send(
            $user,
            'Activez votre compte FoodTracker',
            "Bienvenue sur FoodTracker !\n\n"
            ."Cliquez sur ce lien pour activer votre compte (valable 24h) :\n{$link}\n\n"
            ."Si vous n'êtes pas à l'origine de cette inscription, ignorez cet email.",
        );
    }

    public function sendPasswordResetEmail(User $user, string $rawToken): void
    {
        $link = sprintf('%s/reset-password?token=%s', $this->frontendUrl, $rawToken);

        $this->send(
            $user,
            'Réinitialisation de votre mot de passe FoodTracker',
            "Vous avez demandé la réinitialisation de votre mot de passe.\n\n"
            ."Cliquez sur ce lien pour choisir un nouveau mot de passe (valable 1h) :\n{$link}\n\n"
            ."Si vous n'êtes pas à l'origine de cette demande, ignorez cet email.",
        );
    }

    public function sendPasswordChangeConfirmationEmail(User $user, string $rawToken): void
    {
        $link = sprintf('%s/confirm-password-change?token=%s', $this->frontendUrl, $rawToken);

        $this->send(
            $user,
            'Confirmez le changement de votre mot de passe FoodTracker',
            "Vous avez demandé à changer votre mot de passe.\n\n"
            ."Cliquez sur ce lien pour confirmer ce changement (valable 1h) :\n{$link}\n\n"
            ."Si vous n'êtes pas à l'origine de cette demande, ignorez cet email : votre mot de passe actuel reste inchangé.",
        );
    }

    public function sendPasswordChangedNotification(User $user): void
    {
        $this->send(
            $user,
            'Votre mot de passe FoodTracker a été modifié',
            "Le mot de passe de votre compte FoodTracker vient d'être modifié.\n\n"
            ."Si vous n'êtes pas à l'origine de ce changement, réinitialisez immédiatement votre mot de passe via la page \"mot de passe oublié\".",
        );
    }

    private function send(User $user, string $subject, string $text): void
    {
        $email = (new Email())
            ->from($this->fromAddress)
            ->to($user->getEmail())
            ->subject($subject)
            ->text($text);

        try {
            $this->mailer->send($email);
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Failed to send auth email: {message}', [
                'message' => $e->getMessage(),
                'userId' => $user->getId(),
            ]);
        }
    }
}
