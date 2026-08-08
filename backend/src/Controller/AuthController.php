<?php

namespace App\Controller;

use App\Entity\User;
use App\EventSubscriber\JwtCookieSubscriber;
use App\Mail\AuthMailer;
use App\Security\TokenGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api')]
final class AuthController extends AbstractController
{
    private const VERIFICATION_TOKEN_TTL = '+24 hours';

    #[Route('/register', name: 'app_register', methods: ['POST'])]
    public function register(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher,
        ValidatorInterface $validator,
        TokenGenerator $tokenGenerator,
        AuthMailer $authMailer,
        #[Autowire(service: 'limiter.register_ip')] RateLimiterFactory $registerLimiterFactory,
    ): JsonResponse {
        $limit = $registerLimiterFactory->create($request->getClientIp())->consume();
        if (!$limit->isAccepted()) {
            return $this->json(['error' => 'too many registration attempts, please try again later'], Response::HTTP_TOO_MANY_REQUESTS);
        }

        $payload = json_decode($request->getContent(), true) ?? [];
        $email = trim((string) ($payload['email'] ?? ''));
        $password = (string) ($payload['password'] ?? '');

        if ('' === $email || '' === $password) {
            return $this->json(['error' => 'email and password are required'], Response::HTTP_BAD_REQUEST);
        }

        if (strlen($password) < 8) {
            return $this->json(['error' => 'password must be at least 8 characters'], Response::HTTP_BAD_REQUEST);
        }

        $existing = $em->getRepository(User::class)->findOneBy(['email' => $email]);
        if (null !== $existing) {
            return $this->json(['error' => 'an account with this email already exists'], Response::HTTP_CONFLICT);
        }

        $user = new User();
        $user->setEmail($email);
        $user->setPassword($passwordHasher->hashPassword($user, $password));

        $errors = $validator->validate($user);
        if (count($errors) > 0) {
            return $this->json(['error' => (string) $errors], Response::HTTP_BAD_REQUEST);
        }

        $rawToken = $tokenGenerator->generate();
        $user->setVerificationToken(
            $tokenGenerator->hash($rawToken),
            new \DateTimeImmutable(self::VERIFICATION_TOKEN_TTL),
        );

        $em->persist($user);
        $em->flush();

        $authMailer->sendVerificationEmail($user, $rawToken);

        // Registration only creates the account — it stays unusable until the
        // user clicks the emailed activation link (AuthUserChecker enforces
        // this at login time), so there's no automatic follow-up login here.
        return $this->json(['id' => $user->getId(), 'email' => $user->getEmail()], Response::HTTP_CREATED);
    }

    #[Route('/verify-email', name: 'app_verify_email', methods: ['POST'])]
    public function verifyEmail(
        Request $request,
        EntityManagerInterface $em,
        TokenGenerator $tokenGenerator,
    ): JsonResponse {
        $payload = json_decode($request->getContent(), true) ?? [];
        $token = (string) ($payload['token'] ?? '');

        if ('' === $token) {
            return $this->json(['error' => 'token is required'], Response::HTTP_BAD_REQUEST);
        }

        $user = $em->getRepository(User::class)->findOneBy(['verificationTokenHash' => $tokenGenerator->hash($token)]);

        if (null === $user
            || null === $user->getVerificationTokenExpiresAt()
            || $user->getVerificationTokenExpiresAt() < new \DateTimeImmutable()
        ) {
            return $this->json(['error' => 'invalid or expired token'], Response::HTTP_BAD_REQUEST);
        }

        $user->setIsVerified(true);
        $user->setVerificationToken(null, null);
        $em->flush();

        return $this->json(['success' => true]);
    }

    #[Route('/resend-verification', name: 'app_resend_verification', methods: ['POST'])]
    public function resendVerification(
        Request $request,
        EntityManagerInterface $em,
        TokenGenerator $tokenGenerator,
        AuthMailer $authMailer,
        #[Autowire(service: 'limiter.resend_verification_ip')] RateLimiterFactory $resendVerificationLimiterFactory,
    ): JsonResponse {
        $limit = $resendVerificationLimiterFactory->create($request->getClientIp())->consume();
        if (!$limit->isAccepted()) {
            return $this->json(['error' => 'too many attempts, please try again later'], Response::HTTP_TOO_MANY_REQUESTS);
        }

        $payload = json_decode($request->getContent(), true) ?? [];
        $email = trim((string) ($payload['email'] ?? ''));

        // Always the same response, whether or not the account exists or is
        // already verified — avoids leaking which emails are registered.
        $response = $this->json(['message' => 'if an unverified account exists for this email, a new activation link has been sent']);

        if ('' === $email) {
            return $response;
        }

        $user = $em->getRepository(User::class)->findOneBy(['email' => $email]);
        if (null === $user || $user->isVerified()) {
            return $response;
        }

        $rawToken = $tokenGenerator->generate();
        $user->setVerificationToken(
            $tokenGenerator->hash($rawToken),
            new \DateTimeImmutable(self::VERIFICATION_TOKEN_TTL),
        );
        $em->flush();

        $authMailer->sendVerificationEmail($user, $rawToken);

        return $response;
    }

    /**
     * Never actually executed: the `api` firewall's json_login authenticator
     * intercepts POST /api/login before routing reaches this controller.
     * The route still needs to exist so the router doesn't 404 first.
     */
    #[Route('/login', name: 'app_login', methods: ['POST'])]
    public function login(): void
    {
        throw new \LogicException('This should never be reached — handled by the json_login authenticator.');
    }

    #[Route('/me', name: 'app_me', methods: ['GET'])]
    public function me(Security $security): JsonResponse
    {
        /** @var User $user */
        $user = $security->getUser();

        return $this->json([
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'dailyCalorieGoal' => $user->getDailyCalorieGoal(),
            'heightCm' => $user->getHeightCm(),
            'weightKg' => $user->getWeightKg(),
            'age' => $user->getAge(),
            'sex' => $user->getSex()?->value,
            'activityLevel' => $user->getActivityLevel()?->value,
        ]);
    }

    #[Route('/logout', name: 'app_logout', methods: ['POST'])]
    public function logout(): JsonResponse
    {
        $response = $this->json(['success' => true]);
        $response->headers->clearCookie(JwtCookieSubscriber::COOKIE_NAME, '/');

        return $response;
    }
}
