<?php

namespace App\Service;

use App\Entity\Enum\FoodUnit;
use App\Entity\Food;

final class NutritionCalculator
{
    /**
     * @return array{kcal: float, protein: float, carbs: float, fat: float}
     */
    public function computeSnapshot(Food $food, float $quantity, FoodUnit $unit): array
    {
        $grams = $food->gramsFor($quantity, $unit);
        $factor = $grams / 100.0;

        return [
            'kcal' => round($food->getKcalPer100() * $factor, 2),
            'protein' => round($food->getProteinPer100() * $factor, 2),
            'carbs' => round($food->getCarbsPer100() * $factor, 2),
            'fat' => round($food->getFatPer100() * $factor, 2),
        ];
    }
}
