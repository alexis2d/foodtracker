<?php

namespace App\Controller;

use App\Entity\Enum\FoodSource;
use App\Entity\Enum\FoodUnit;
use App\Entity\Food;
use App\Entity\User;
use App\Repository\FoodRepository;
use App\Repository\MealRepository;
use App\Service\FoodPresenter;
use App\Service\MealSyncService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/custom-foods')]
final class CustomFoodController extends AbstractController
{
    public function __construct(
        private readonly FoodRepository $foodRepository,
        private readonly MealRepository $mealRepository,
        private readonly MealSyncService $mealSync,
        private readonly FoodPresenter $presenter,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('', methods: ['GET'])]
    public function list(#[CurrentUser] User $user): JsonResponse
    {
        $foods = $this->foodRepository->findCustomForOwner($user);

        return $this->json(['results' => array_map(fn (Food $f) => $this->presenter->present($f), $foods)]);
    }

    #[Route('', methods: ['POST'])]
    public function create(Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $food = new Food();
        $food->setSource(FoodSource::Custom);
        $food->setOwnerUser($user);

        $error = $this->applyPayload($food, $request);
        if (null !== $error) {
            return $this->json(['error' => $error], Response::HTTP_BAD_REQUEST);
        }

        $this->em->persist($food);
        $this->em->flush();

        return $this->json($this->presenter->present($food), Response::HTTP_CREATED);
    }

    #[Route('/{id}', methods: ['GET'])]
    public function get(int $id, #[CurrentUser] User $user): JsonResponse
    {
        $food = $this->findOwned($id, $user);
        if (null === $food) {
            return $this->json(['error' => 'not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->presenter->present($food));
    }

    #[Route('/{id}', methods: ['PUT', 'PATCH'])]
    public function update(int $id, Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $food = $this->findOwned($id, $user);
        if (null === $food) {
            return $this->json(['error' => 'not found'], Response::HTTP_NOT_FOUND);
        }

        $error = $this->applyPayload($food, $request);
        if (null !== $error) {
            return $this->json(['error' => $error], Response::HTTP_BAD_REQUEST);
        }

        $food->touch();
        // This food's macros may have just changed, so any meal built from
        // it (directly or via another meal) has stale totals until resynced.
        $this->mealSync->cascadeFromFood($food);
        $this->em->flush();

        return $this->json($this->presenter->present($food));
    }

    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(int $id, #[CurrentUser] User $user): JsonResponse
    {
        $food = $this->findOwned($id, $user);
        if (null === $food) {
            return $this->json(['error' => 'not found'], Response::HTTP_NOT_FOUND);
        }

        if ([] !== $this->mealRepository->findUsingFood($food)) {
            return $this->json(['error' => 'cannot delete: this food is used as an ingredient in one or more meals'], Response::HTTP_CONFLICT);
        }

        $this->em->remove($food);
        $this->em->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    private function findOwned(int $id, User $user): ?Food
    {
        $food = $this->foodRepository->find($id);
        if (null === $food || FoodSource::Custom !== $food->getSource() || $food->getOwnerUser()?->getId() !== $user->getId()) {
            return null;
        }

        return $food;
    }

    private function applyPayload(Food $food, Request $request): ?string
    {
        $payload = json_decode($request->getContent(), true) ?? [];

        $name = trim((string) ($payload['name'] ?? ''));
        if ('' === $name) {
            return 'name is required';
        }

        $unitValue = (string) ($payload['defaultUnit'] ?? 'g');
        $unit = FoodUnit::tryFrom($unitValue);
        if (null === $unit) {
            return 'defaultUnit must be one of: g, ml, unit';
        }

        $unitWeightGrams = null;
        if (FoodUnit::Unit === $unit) {
            if (!isset($payload['unitWeightGrams']) || !is_numeric($payload['unitWeightGrams']) || (float) $payload['unitWeightGrams'] <= 0) {
                return 'unitWeightGrams (grams per unit) is required when defaultUnit is "unit"';
            }
            $unitWeightGrams = (float) $payload['unitWeightGrams'];
        }

        foreach (['kcalPer100', 'proteinPer100', 'carbsPer100', 'fatPer100'] as $field) {
            if (!isset($payload[$field]) || !is_numeric($payload[$field]) || (float) $payload[$field] < 0) {
                return sprintf('%s is required and must be a non-negative number', $field);
            }
        }

        $fiber = null;
        if (isset($payload['fiberPer100']) && '' !== $payload['fiberPer100'] && null !== $payload['fiberPer100']) {
            if (!is_numeric($payload['fiberPer100']) || (float) $payload['fiberPer100'] < 0) {
                return 'fiberPer100 must be a non-negative number';
            }
            $fiber = (float) $payload['fiberPer100'];
        }

        $food->setName($name);
        $food->setDefaultUnit($unit);
        $food->setUnitWeightGrams($unitWeightGrams);
        $food->setKcalPer100((float) $payload['kcalPer100']);
        $food->setProteinPer100((float) $payload['proteinPer100']);
        $food->setCarbsPer100((float) $payload['carbsPer100']);
        $food->setFatPer100((float) $payload['fatPer100']);
        $food->setFiberPer100($fiber);

        return null;
    }
}
