<?php

namespace App\Controller;

use App\Entity\User;
use App\Mail\AuthMailer;
use App\Security\TokenGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
final class PasswordController extends AbstractController
{
    private const RESET_TOKEN_TTL = '+1 hour';
    private const CHANGE_TOKEN_TTL = '+1 hour';

    #[Route('/forgot-password', name: 'app_forgot_password', methods: ['POST'])]
    public function forgotPassword(
        Request $request,
        EntityManagerInterface $em,
        TokenGenerator $tokenGenerator,
        AuthMailer $authMailer,
        #[Autowire(service: 'limiter.forgot_password_ip')] RateLimiterFactory $forgotPasswordLimiterFactory,
    ): JsonResponse {
        $limit = $forgotPasswordLimiterFactory->create($request->getClientIp())->consume();
        if (!$limit->isAccepted()) {
            return $this->json(['error' => 'too many attempts, please try again later'], Response::HTTP_TOO_MANY_REQUESTS);
        }

        $payload = json_decode($request->getContent(), true) ?? [];
        $email = trim((string) ($payload['email'] ?? ''));

        // Always the same response, whether or not the account exists —
        // avoids leaking which emails are registered.
        $response = $this->json(['message' => 'if an account exists for this email, a reset link has been sent']);

        if ('' === $email) {
            return $response;
        }

        $user = $em->getRepository(User::class)->findOneBy(['email' => $email]);
        if (null === $user) {
            return $response;
        }

        $rawToken = $tokenGenerator->generate();
        $user->setPasswordResetToken(
            $tokenGenerator->hash($rawToken),
            new \DateTimeImmutable(self::RESET_TOKEN_TTL),
        );
        $em->flush();

        $authMailer->sendPasswordResetEmail($user, $rawToken);

        return $response;
    }

    #[Route('/reset-password', name: 'app_reset_password', methods: ['POST'])]
    public function resetPassword(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher,
        TokenGenerator $tokenGenerator,
        AuthMailer $authMailer,
    ): JsonResponse {
        $payload = json_decode($request->getContent(), true) ?? [];
        $token = (string) ($payload['token'] ?? '');
        $password = (string) ($payload['password'] ?? '');

        if ('' === $token || '' === $password) {
            return $this->json(['error' => 'token and password are required'], Response::HTTP_BAD_REQUEST);
        }

        if (strlen($password) < 8) {
            return $this->json(['error' => 'password must be at least 8 characters'], Response::HTTP_BAD_REQUEST);
        }

        $user = $em->getRepository(User::class)->findOneBy(['passwordResetTokenHash' => $tokenGenerator->hash($token)]);

        if (null === $user
            || null === $user->getPasswordResetTokenExpiresAt()
            || $user->getPasswordResetTokenExpiresAt() < new \DateTimeImmutable()
        ) {
            return $this->json(['error' => 'invalid or expired token'], Response::HTTP_BAD_REQUEST);
        }

        $user->setPassword($passwordHasher->hashPassword($user, $password));
        $user->setPasswordResetToken(null, null);
        // Invalidate any in-flight "change password" request too.
        $user->setPendingPasswordChange(null, null, null);
        // Possessing a valid reset link proves ownership of the email address.
        $user->setIsVerified(true);
        $em->flush();

        $authMailer->sendPasswordChangedNotification($user);

        return $this->json(['success' => true]);
    }

    #[Route('/change-password', name: 'app_change_password', methods: ['POST'])]
    public function changePassword(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher,
        TokenGenerator $tokenGenerator,
        AuthMailer $authMailer,
        Security $security,
        #[Autowire(service: 'limiter.password_change_user')] RateLimiterFactory $passwordChangeLimiterFactory,
    ): JsonResponse {
        /** @var User $user */
        $user = $security->getUser();

        $limit = $passwordChangeLimiterFactory->create($user->getUserIdentifier())->consume();
        if (!$limit->isAccepted()) {
            return $this->json(['error' => 'too many attempts, please try again later'], Response::HTTP_TOO_MANY_REQUESTS);
        }

        $payload = json_decode($request->getContent(), true) ?? [];
        $currentPassword = (string) ($payload['currentPassword'] ?? '');
        $newPassword = (string) ($payload['newPassword'] ?? '');

        if (!$passwordHasher->isPasswordValid($user, $currentPassword)) {
            return $this->json(['error' => 'current password is incorrect'], Response::HTTP_BAD_REQUEST);
        }

        if (strlen($newPassword) < 8) {
            return $this->json(['error' => 'password must be at least 8 characters'], Response::HTTP_BAD_REQUEST);
        }

        $rawToken = $tokenGenerator->generate();
        $user->setPendingPasswordChange(
            $passwordHasher->hashPassword($user, $newPassword),
            $tokenGenerator->hash($rawToken),
            new \DateTimeImmutable(self::CHANGE_TOKEN_TTL),
        );
        $em->flush();

        $authMailer->sendPasswordChangeConfirmationEmail($user, $rawToken);

        return $this->json(['message' => 'confirmation email sent']);
    }

    #[Route('/confirm-password-change', name: 'app_confirm_password_change', methods: ['POST'])]
    public function confirmPasswordChange(
        Request $request,
        EntityManagerInterface $em,
        TokenGenerator $tokenGenerator,
        AuthMailer $authMailer,
    ): JsonResponse {
        $payload = json_decode($request->getContent(), true) ?? [];
        $token = (string) ($payload['token'] ?? '');

        if ('' === $token) {
            return $this->json(['error' => 'token is required'], Response::HTTP_BAD_REQUEST);
        }

        $user = $em->getRepository(User::class)->findOneBy(['passwordChangeTokenHash' => $tokenGenerator->hash($token)]);

        if (null === $user
            || null === $user->getPasswordChangeTokenExpiresAt()
            || $user->getPasswordChangeTokenExpiresAt() < new \DateTimeImmutable()
            || null === $user->getPendingPasswordHash()
        ) {
            return $this->json(['error' => 'invalid or expired token'], Response::HTTP_BAD_REQUEST);
        }

        $user->setPassword($user->getPendingPasswordHash());
        $user->setPendingPasswordChange(null, null, null);
        $em->flush();

        $authMailer->sendPasswordChangedNotification($user);

        return $this->json(['success' => true]);
    }
}
