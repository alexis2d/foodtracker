<?php

namespace App\Controller;

use App\Entity\DiaryEntry;
use App\Entity\Enum\FoodSource;
use App\Entity\Enum\FoodUnit;
use App\Entity\Enum\MealType;
use App\Entity\User;
use App\Repository\DiaryEntryRepository;
use App\Repository\FoodRepository;
use App\Service\FoodPresenter;
use App\Service\NutritionCalculator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/diary')]
final class DiaryController extends AbstractController
{
    public function __construct(
        private readonly DiaryEntryRepository $diaryEntryRepository,
        private readonly FoodRepository $foodRepository,
        private readonly NutritionCalculator $calculator,
        private readonly FoodPresenter $foodPresenter,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('', methods: ['GET'])]
    public function list(Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $date = $this->parseDate($request->query->get('date'));
        if (null === $date) {
            return $this->json(['error' => 'date is required (YYYY-MM-DD)'], Response::HTTP_BAD_REQUEST);
        }

        $entries = $this->diaryEntryRepository->findForUserOnDate($user, $date);

        return $this->json(['results' => array_map(fn (DiaryEntry $e) => $this->present($e), $entries)]);
    }

    #[Route('', methods: ['POST'])]
    public function create(Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $payload = json_decode($request->getContent(), true) ?? [];

        $food = $this->resolveLoggableFood($payload['foodId'] ?? null, $user);
        if (null === $food) {
            return $this->json(['error' => 'foodId is required and must reference an existing, accessible food'], Response::HTTP_BAD_REQUEST);
        }

        [$quantity, $unit, $mealType, $consumedAt, $error] = $this->parseEntryFields($payload);
        if (null !== $error) {
            return $this->json(['error' => $error], Response::HTTP_BAD_REQUEST);
        }

        $entry = new DiaryEntry();
        $entry->setUser($user);
        $entry->setFood($food);
        $entry->setQuantity($quantity);
        $entry->setUnit($unit);
        $entry->setMealType($mealType);
        $entry->setConsumedAt($consumedAt);
        $this->applySnapshot($entry, $food, $quantity, $unit);

        $this->em->persist($entry);
        $this->em->flush();

        return $this->json($this->present($entry), Response::HTTP_CREATED);
    }

    #[Route('/summary', methods: ['GET'])]
    public function summary(Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $date = $this->parseDate($request->query->get('date'));
        if (null === $date) {
            return $this->json(['error' => 'date is required (YYYY-MM-DD)'], Response::HTTP_BAD_REQUEST);
        }

        $entries = $this->diaryEntryRepository->findForUserOnDate($user, $date);

        $totals = ['kcal' => 0.0, 'protein' => 0.0, 'carbs' => 0.0, 'fat' => 0.0];
        $entriesByMeal = ['breakfast' => [], 'lunch' => [], 'dinner' => [], 'snack' => []];

        foreach ($entries as $entry) {
            $totals['kcal'] += $entry->getKcalAtLogging();
            $totals['protein'] += $entry->getProteinAtLogging();
            $totals['carbs'] += $entry->getCarbsAtLogging();
            $totals['fat'] += $entry->getFatAtLogging();
            $entriesByMeal[$entry->getMealType()->value][] = $this->present($entry);
        }

        $goalKcal = $user->getDailyCalorieGoal();

        return $this->json([
            'date' => $date->format('Y-m-d'),
            'totals' => $totals,
            'goal' => ['kcal' => $goalKcal],
            'remaining' => ['kcal' => round($goalKcal - $totals['kcal'], 2)],
            'entriesByMeal' => $entriesByMeal,
        ]);
    }

    #[Route('/{id}', methods: ['PUT', 'PATCH'])]
    public function update(int $id, Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $entry = $this->findOwned($id, $user);
        if (null === $entry) {
            return $this->json(['error' => 'not found'], Response::HTTP_NOT_FOUND);
        }

        $payload = json_decode($request->getContent(), true) ?? [];
        $payload += [
            'quantity' => $entry->getQuantity(),
            'unit' => $entry->getUnit()->value,
            'mealType' => $entry->getMealType()->value,
            'consumedAt' => $entry->getConsumedAt()->format('Y-m-d'),
        ];

        [$quantity, $unit, $mealType, $consumedAt, $error] = $this->parseEntryFields($payload);
        if (null !== $error) {
            return $this->json(['error' => $error], Response::HTTP_BAD_REQUEST);
        }

        $entry->setQuantity($quantity);
        $entry->setUnit($unit);
        $entry->setMealType($mealType);
        $entry->setConsumedAt($consumedAt);
        $this->applySnapshot($entry, $entry->getFood(), $quantity, $unit);

        $this->em->flush();

        return $this->json($this->present($entry));
    }

    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(int $id, #[CurrentUser] User $user): JsonResponse
    {
        $entry = $this->findOwned($id, $user);
        if (null === $entry) {
            return $this->json(['error' => 'not found'], Response::HTTP_NOT_FOUND);
        }

        $this->em->remove($entry);
        $this->em->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    private function resolveLoggableFood(mixed $foodId, User $user): ?\App\Entity\Food
    {
        if (!is_numeric($foodId)) {
            return null;
        }

        $food = $this->foodRepository->find((int) $foodId);
        if (null === $food) {
            return null;
        }

        // Custom foods and composed meals are only loggable by their owner; off/seed foods are shared.
        if (in_array($food->getSource(), [FoodSource::Custom, FoodSource::Meal], true) && $food->getOwnerUser()?->getId() !== $user->getId()) {
            return null;
        }

        return $food;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array{0: float, 1: ?FoodUnit, 2: ?MealType, 3: ?\DateTimeImmutable, 4: ?string}
     */
    private function parseEntryFields(array $payload): array
    {
        $quantity = $payload['quantity'] ?? null;
        if (!is_numeric($quantity) || (float) $quantity <= 0) {
            return [0.0, null, null, null, 'quantity must be a positive number'];
        }

        $unit = FoodUnit::tryFrom((string) ($payload['unit'] ?? ''));
        if (null === $unit) {
            return [0.0, null, null, null, 'unit must be one of: g, ml, unit'];
        }

        $mealType = MealType::tryFrom((string) ($payload['mealType'] ?? ''));
        if (null === $mealType) {
            return [0.0, null, null, null, 'mealType must be one of: breakfast, lunch, dinner, snack'];
        }

        $consumedAt = $this->parseDate($payload['consumedAt'] ?? null);
        if (null === $consumedAt) {
            return [0.0, null, null, null, 'consumedAt is required (YYYY-MM-DD)'];
        }

        return [(float) $quantity, $unit, $mealType, $consumedAt, null];
    }

    private function applySnapshot(DiaryEntry $entry, \App\Entity\Food $food, float $quantity, FoodUnit $unit): void
    {
        $snapshot = $this->calculator->computeSnapshot($food, $quantity, $unit);
        $entry->setSnapshot($snapshot['kcal'], $snapshot['protein'], $snapshot['carbs'], $snapshot['fat']);
    }

    private function findOwned(int $id, User $user): ?DiaryEntry
    {
        $entry = $this->diaryEntryRepository->find($id);
        if (null === $entry || $entry->getUser()->getId() !== $user->getId()) {
            return null;
        }

        return $entry;
    }

    private function parseDate(?string $value): ?\DateTimeImmutable
    {
        if (null === $value || '' === $value) {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return false !== $date ? $date : null;
    }

    private function present(DiaryEntry $entry): array
    {
        return [
            'id' => $entry->getId(),
            'food' => $this->foodPresenter->present($entry->getFood()),
            'quantity' => $entry->getQuantity(),
            'unit' => $entry->getUnit()->value,
            'mealType' => $entry->getMealType()->value,
            'consumedAt' => $entry->getConsumedAt()->format('Y-m-d'),
            'kcal' => $entry->getKcalAtLogging(),
            'protein' => $entry->getProteinAtLogging(),
            'carbs' => $entry->getCarbsAtLogging(),
            'fat' => $entry->getFatAtLogging(),
        ];
    }
}
