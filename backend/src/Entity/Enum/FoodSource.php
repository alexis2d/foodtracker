<?php

namespace App\Entity\Enum;

enum FoodSource: string
{
    case Custom = 'custom';
    case OpenFoodFacts = 'off';
    case Seed = 'seed';
    case Meal = 'meal';
}
