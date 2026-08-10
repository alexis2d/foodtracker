<?php

namespace App\Service;

use App\Entity\Enum\FoodUnit;
use App\Entity\Food;

final class MealCalculator
{
    public function __construct(
        private readonly NutritionCalculator $nutritionCalculator,
    ) {
    }

    /**
     * @param array<int, array{food: Food, quantity: float, unit: FoodUnit}> $ingredients
     *
     * @return array{totalGrams: float, kcal: float, protein: float, carbs: float, fat: float, items: array<int, array{grams: float, kcal: float, protein: float, carbs: float, fat: float}>}
     */
    public function computeAggregate(array $ingredients): array
    {
        $totalGrams = 0.0;
        $totals = ['kcal' => 0.0, 'protein' => 0.0, 'carbs' => 0.0, 'fat' => 0.0];
        $items = [];

        foreach ($ingredients as $ingredient) {
            $food = $ingredient['food'];
            $grams = $food->gramsFor($ingredient['quantity'], $ingredient['unit']);
            $snapshot = $this->nutritionCalculator->computeSnapshot($food, $ingredient['quantity'], $ingredient['unit']);

            $totalGrams += $grams;
            $totals['kcal'] += $snapshot['kcal'];
            $totals['protein'] += $snapshot['protein'];
            $totals['carbs'] += $snapshot['carbs'];
            $totals['fat'] += $snapshot['fat'];

            $items[] = array_merge(['grams' => round($grams, 2)], $snapshot);
        }

        return [
            'totalGrams' => $totalGrams,
            'kcal' => $totals['kcal'],
            'protein' => $totals['protein'],
            'carbs' => $totals['carbs'],
            'fat' => $totals['fat'],
            'items' => $items,
        ];
    }

    /**
     * @param array{totalGrams: float, kcal: float, protein: float, carbs: float, fat: float} $aggregate
     *
     * @return array{kcalPer100: float, proteinPer100: float, carbsPer100: float, fatPer100: float}
     */
    public function per100FromAggregate(array $aggregate): array
    {
        $factor = 100.0 / $aggregate['totalGrams'];

        return [
            'kcalPer100' => round($aggregate['kcal'] * $factor, 2),
            'proteinPer100' => round($aggregate['protein'] * $factor, 2),
            'carbsPer100' => round($aggregate['carbs'] * $factor, 2),
            'fatPer100' => round($aggregate['fat'] * $factor, 2),
        ];
    }
}
