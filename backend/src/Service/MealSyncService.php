<?php

namespace App\Service;

use App\Entity\Food;
use App\Entity\Meal;
use App\Entity\MealIngredient;
use App\Repository\MealRepository;

final class MealSyncService
{
    public function __construct(
        private readonly MealRepository $mealRepository,
        private readonly MealCalculator $calculator,
    ) {
    }

    /**
     * Recomputes a meal's linked Food (per-100 macros) from its current
     * ingredients. Callers are responsible for flushing.
     */
    public function recomputeMeal(Meal $meal): void
    {
        $ingredients = array_map(
            static fn (MealIngredient $mi) => ['food' => $mi->getFood(), 'quantity' => $mi->getQuantity(), 'unit' => $mi->getUnit()],
            $meal->getIngredients()->toArray(),
        );

        $aggregate = $this->calculator->computeAggregate($ingredients);
        $per100 = $this->calculator->per100FromAggregate($aggregate);

        $food = $meal->getFood();
        $food->setKcalPer100($per100['kcalPer100']);
        $food->setProteinPer100($per100['proteinPer100']);
        $food->setCarbsPer100($per100['carbsPer100']);
        $food->setFatPer100($per100['fatPer100']);
        $food->touch();
        $meal->touch();
    }

    /**
     * Whenever $food's macros change (a custom food is edited, or a meal's
     * own linked Food is recomputed above), every meal that uses $food as an
     * ingredient has stale totals until it's resynced too — and since a meal
     * can itself be used as an ingredient in another meal, this has to walk
     * the dependency graph transitively, not just one level.
     *
     * $visited is keyed by Food id and guards against two meals referencing
     * each other's Food as ingredients, which would otherwise recurse forever.
     *
     * @param array<int, true> $visited
     */
    public function cascadeFromFood(Food $food, array $visited = []): void
    {
        if (null === $food->getId() || isset($visited[$food->getId()])) {
            return;
        }
        $visited[$food->getId()] = true;

        foreach ($this->mealRepository->findUsingFood($food) as $meal) {
            $this->recomputeMeal($meal);
            $this->cascadeFromFood($meal->getFood(), $visited);
        }
    }
}
