<?php

namespace App\Controller;

use App\Entity\User;
use App\EventSubscriber\JwtCookieSubscriber;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api')]
final class AuthController extends AbstractController
{
    #[Route('/register', name: 'app_register', methods: ['POST'])]
    public function register(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher,
        ValidatorInterface $validator,
    ): JsonResponse {
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

        $em->persist($user);
        $em->flush();

        // Registration only creates the account. The frontend follows up with
        // a normal /api/login call (handled by the json_login authenticator)
        // to obtain the auth cookie.
        return $this->json(['id' => $user->getId(), 'email' => $user->getEmail()], Response::HTTP_CREATED);
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
