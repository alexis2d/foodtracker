<?php

namespace App\Controller;

use App\Entity\Enum\ActivityLevel;
use App\Entity\Enum\Sex;
use App\Entity\User;
use App\Service\CalorieGoalCalculator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/profile')]
final class ProfileController extends AbstractController
{
    public function __construct(
        private readonly CalorieGoalCalculator $calculator,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('', methods: ['GET'])]
    public function get(#[CurrentUser] User $user): JsonResponse
    {
        return $this->json($this->present($user));
    }

    /**
     * Computes a suggested daily calorie goal from body measurements + activity level.
     * Pure calculation: does not persist anything.
     */
    #[Route('/calculate-goal', methods: ['POST'])]
    public function calculateGoal(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true) ?? [];

        [$height, $weight, $age, $sex, $activityLevel, $error] = $this->parseProfileFields($payload);
        if (null !== $error) {
            return $this->json(['error' => $error], Response::HTTP_BAD_REQUEST);
        }
        if (null === $height || null === $weight || null === $age || null === $sex || null === $activityLevel) {
            return $this->json(['error' => 'heightCm, weightKg, age, sex and activityLevel are all required'], Response::HTTP_BAD_REQUEST);
        }

        $goal = $this->calculator->calculate($height, $weight, $age, $sex, $activityLevel);

        return $this->json(['dailyCalorieGoal' => $goal]);
    }

    /**
     * Partially updates the profile fields and/or the daily calorie goal.
     * Any field omitted from the payload is left untouched.
     */
    #[Route('', methods: ['PUT'])]
    public function update(Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $payload = json_decode($request->getContent(), true) ?? [];

        [$height, $weight, $age, $sex, $activityLevel, $error] = $this->parseProfileFields($payload);
        if (null !== $error) {
            return $this->json(['error' => $error], Response::HTTP_BAD_REQUEST);
        }

        if (array_key_exists('heightCm', $payload)) {
            $user->setHeightCm($height);
        }
        if (array_key_exists('weightKg', $payload)) {
            $user->setWeightKg($weight);
        }
        if (array_key_exists('age', $payload)) {
            $user->setAge($age);
        }
        if (array_key_exists('sex', $payload)) {
            $user->setSex($sex);
        }
        if (array_key_exists('activityLevel', $payload)) {
            $user->setActivityLevel($activityLevel);
        }

        if (array_key_exists('dailyCalorieGoal', $payload)) {
            $goal = $payload['dailyCalorieGoal'];
            if (!is_numeric($goal) || (int) $goal < 500 || (int) $goal > 10000) {
                return $this->json(['error' => 'dailyCalorieGoal must be a number between 500 and 10000'], Response::HTTP_BAD_REQUEST);
            }
            $user->setDailyCalorieGoal((int) $goal);
        }

        $this->em->flush();

        return $this->json($this->present($user));
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array{0: ?int, 1: ?float, 2: ?int, 3: ?Sex, 4: ?ActivityLevel, 5: ?string}
     */
    private function parseProfileFields(array $payload): array
    {
        $height = null;
        if (array_key_exists('heightCm', $payload) && null !== $payload['heightCm']) {
            if (!is_numeric($payload['heightCm']) || (int) $payload['heightCm'] < 50 || (int) $payload['heightCm'] > 250) {
                return [null, null, null, null, null, 'heightCm must be a number between 50 and 250'];
            }
            $height = (int) $payload['heightCm'];
        }

        $weight = null;
        if (array_key_exists('weightKg', $payload) && null !== $payload['weightKg']) {
            if (!is_numeric($payload['weightKg']) || (float) $payload['weightKg'] < 20 || (float) $payload['weightKg'] > 300) {
                return [null, null, null, null, null, 'weightKg must be a number between 20 and 300'];
            }
            $weight = (float) $payload['weightKg'];
        }

        $age = null;
        if (array_key_exists('age', $payload) && null !== $payload['age']) {
            if (!is_numeric($payload['age']) || (int) $payload['age'] < 10 || (int) $payload['age'] > 120) {
                return [null, null, null, null, null, 'age must be a number between 10 and 120'];
            }
            $age = (int) $payload['age'];
        }

        $sex = null;
        if (array_key_exists('sex', $payload) && null !== $payload['sex']) {
            $sex = Sex::tryFrom((string) $payload['sex']);
            if (null === $sex) {
                return [null, null, null, null, null, 'sex must be one of: male, female'];
            }
        }

        $activityLevel = null;
        if (array_key_exists('activityLevel', $payload) && null !== $payload['activityLevel']) {
            $activityLevel = ActivityLevel::tryFrom((string) $payload['activityLevel']);
            if (null === $activityLevel) {
                return [null, null, null, null, null, 'activityLevel must be one of: sedentary, light, moderate, active, very_active'];
            }
        }

        return [$height, $weight, $age, $sex, $activityLevel, null];
    }

    private function present(User $user): array
    {
        return [
            'dailyCalorieGoal' => $user->getDailyCalorieGoal(),
            'heightCm' => $user->getHeightCm(),
            'weightKg' => $user->getWeightKg(),
            'age' => $user->getAge(),
            'sex' => $user->getSex()?->value,
            'activityLevel' => $user->getActivityLevel()?->value,
        ];
    }
}
