<?php

namespace App\Entity\Enum;

enum FoodUnit: string
{
    case Gram = 'g';
    case Milliliter = 'ml';
    case Unit = 'unit';
}
