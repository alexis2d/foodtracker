<?php

namespace App\Service;

use App\Entity\Enum\ActivityLevel;
use App\Entity\Enum\Sex;

final class CalorieGoalCalculator
{
    /**
     * Estimates a daily calorie goal (maintenance TDEE) using the Mifflin-St Jeor
     * BMR formula scaled by an activity multiplier.
     */
    public function calculate(int $heightCm, float $weightKg, int $age, Sex $sex, ActivityLevel $activityLevel): int
    {
        $bmr = 10 * $weightKg + 6.25 * $heightCm - 5 * $age + (Sex::Male === $sex ? 5 : -161);

        return (int) round($bmr * $activityLevel->multiplier());
    }
}
