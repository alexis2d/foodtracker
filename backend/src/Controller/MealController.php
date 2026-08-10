<?php

namespace App\Controller;

use App\Entity\Enum\FoodSource;
use App\Entity\Enum\FoodUnit;
use App\Entity\Food;
use App\Entity\Meal;
use App\Entity\MealIngredient;
use App\Entity\User;
use App\Repository\FoodRepository;
use App\Repository\MealRepository;
use App\Service\FoodPresenter;
use App\Service\MealCalculator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/meals')]
final class MealController extends AbstractController
{
    public function __construct(
        private readonly MealRepository $mealRepository,
        private readonly FoodRepository $foodRepository,
        private readonly MealCalculator $calculator,
        private readonly FoodPresenter $foodPresenter,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('', methods: ['GET'])]
    public function list(#[CurrentUser] User $user): JsonResponse
    {
        $meals = $this->mealRepository->findForOwner($user);

        return $this->json(['results' => array_map(fn (Meal $m) => $this->present($m), $meals)]);
    }

    #[Route('', methods: ['POST'])]
    public function create(Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $payload = json_decode($request->getContent(), true) ?? [];

        $name = trim((string) ($payload['name'] ?? ''));
        if ('' === $name) {
            return $this->json(['error' => 'name is required'], Response::HTTP_BAD_REQUEST);
        }

        [$ingredients, $error] = $this->resolveIngredients($payload['ingredients'] ?? null, $user);
        if (null !== $error) {
            return $this->json(['error' => $error], Response::HTTP_BAD_REQUEST);
        }

        $aggregate = $this->calculator->computeAggregate($ingredients);

        $food = new Food();
        $food->setSource(FoodSource::Meal);
        $food->setOwnerUser($user);
        $food->setName($name);
        $food->setDefaultUnit(FoodUnit::Gram);
        $this->applyPer100($food, $aggregate);

        $meal = new Meal();
        $meal->setName($name);
        $meal->setOwnerUser($user);
        $meal->setFood($food);
        $this->applyIngredients($meal, $ingredients);

        $this->em->persist($food);
        $this->em->persist($meal);
        $this->em->flush();

        return $this->json($this->present($meal), Response::HTTP_CREATED);
    }

    #[Route('/{id}', methods: ['GET'])]
    public function get(int $id, #[CurrentUser] User $user): JsonResponse
    {
        $meal = $this->findOwned($id, $user);
        if (null === $meal) {
            return $this->json(['error' => 'not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->present($meal));
    }

    #[Route('/{id}', methods: ['PUT', 'PATCH'])]
    public function update(int $id, Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $meal = $this->findOwned($id, $user);
        if (null === $meal) {
            return $this->json(['error' => 'not found'], Response::HTTP_NOT_FOUND);
        }

        $payload = json_decode($request->getContent(), true) ?? [];

        $name = trim((string) ($payload['name'] ?? ''));
        if ('' === $name) {
            return $this->json(['error' => 'name is required'], Response::HTTP_BAD_REQUEST);
        }

        [$ingredients, $error] = $this->resolveIngredients($payload['ingredients'] ?? null, $user);
        if (null !== $error) {
            return $this->json(['error' => $error], Response::HTTP_BAD_REQUEST);
        }

        $aggregate = $this->calculator->computeAggregate($ingredients);

        $meal->setName($name);
        $meal->getFood()->setName($name);
        $this->applyPer100($meal->getFood(), $aggregate);
        $meal->getFood()->touch();

        $meal->getIngredients()->clear();
        $this->applyIngredients($meal, $ingredients);
        $meal->touch();

        $this->em->flush();

        return $this->json($this->present($meal));
    }

    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(int $id, #[CurrentUser] User $user): JsonResponse
    {
        $meal = $this->findOwned($id, $user);
        if (null === $meal) {
            return $this->json(['error' => 'not found'], Response::HTTP_NOT_FOUND);
        }

        // The linked Food row is intentionally left in place: it may already be
        // referenced by DiaryEntry rows, whose snapshotted macros must keep working.
        $this->em->remove($meal);
        $this->em->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @param array<int, array{food: Food, quantity: float, unit: FoodUnit}> $ingredients
     */
    private function applyIngredients(Meal $meal, array $ingredients): void
    {
        foreach ($ingredients as $ingredient) {
            $mealIngredient = new MealIngredient();
            $mealIngredient->setFood($ingredient['food']);
            $mealIngredient->setQuantity($ingredient['quantity']);
            $mealIngredient->setUnit($ingredient['unit']);
            $meal->addIngredient($mealIngredient);
            $this->em->persist($mealIngredient);
        }
    }

    /**
     * @param array{kcal: float, protein: float, carbs: float, fat: float, totalGrams: float} $aggregate
     */
    private function applyPer100(Food $food, array $aggregate): void
    {
        $per100 = $this->calculator->per100FromAggregate($aggregate);
        $food->setKcalPer100($per100['kcalPer100']);
        $food->setProteinPer100($per100['proteinPer100']);
        $food->setCarbsPer100($per100['carbsPer100']);
        $food->setFatPer100($per100['fatPer100']);
    }

    /**
     * @return array{0: array<int, array{food: Food, quantity: float, unit: FoodUnit}>, 1: ?string}
     */
    private function resolveIngredients(mixed $rawIngredients, User $user): array
    {
        if (!is_array($rawIngredients) || [] === $rawIngredients) {
            return [[], 'ingredients must be a non-empty array'];
        }

        $ingredients = [];
        foreach ($rawIngredients as $raw) {
            if (!is_array($raw)) {
                return [[], 'each ingredient must be an object with foodId, quantity and unit'];
            }

            $food = $this->resolveIngredientFood($raw['foodId'] ?? null, $user);
            if (null === $food) {
                return [[], 'each ingredient foodId must reference an existing, accessible food'];
            }

            $quantity = $raw['quantity'] ?? null;
            if (!is_numeric($quantity) || (float) $quantity <= 0) {
                return [[], 'each ingredient quantity must be a positive number'];
            }

            $unit = FoodUnit::tryFrom((string) ($raw['unit'] ?? ''));
            if (null === $unit) {
                return [[], 'each ingredient unit must be one of: g, ml, unit'];
            }

            $ingredients[] = ['food' => $food, 'quantity' => (float) $quantity, 'unit' => $unit];
        }

        return [$ingredients, null];
    }

    private function resolveIngredientFood(mixed $foodId, User $user): ?Food
    {
        if (!is_numeric($foodId)) {
            return null;
        }

        $food = $this->foodRepository->find((int) $foodId);
        if (null === $food) {
            return null;
        }

        if (in_array($food->getSource(), [FoodSource::Custom, FoodSource::Meal], true) && $food->getOwnerUser()?->getId() !== $user->getId()) {
            return null;
        }

        return $food;
    }

    private function findOwned(int $id, User $user): ?Meal
    {
        $meal = $this->mealRepository->find($id);
        if (null === $meal || $meal->getOwnerUser()->getId() !== $user->getId()) {
            return null;
        }

        return $meal;
    }

    private function present(Meal $meal): array
    {
        $ingredients = array_map(function (MealIngredient $ingredient) {
            $aggregate = $this->calculator->computeAggregate([[
                'food' => $ingredient->getFood(),
                'quantity' => $ingredient->getQuantity(),
                'unit' => $ingredient->getUnit(),
            ]]);
            $contribution = $aggregate['items'][0];

            return [
                'food' => $this->foodPresenter->present($ingredient->getFood()),
                'quantity' => $ingredient->getQuantity(),
                'unit' => $ingredient->getUnit()->value,
                'contribution' => $contribution,
            ];
        }, $meal->getIngredients()->toArray());

        return [
            'id' => $meal->getId(),
            'name' => $meal->getName(),
            'food' => $this->foodPresenter->present($meal->getFood()),
            'ingredients' => $ingredients,
            'createdAt' => $meal->getCreatedAt()->format('Y-m-d\TH:i:sP'),
            'updatedAt' => $meal->getUpdatedAt()->format('Y-m-d\TH:i:sP'),
        ];
    }
}
