<?php

namespace App\Service;

use App\Entity\Food;

final class FoodPresenter
{
    /**
     * @return array<string, mixed>
     */
    public function present(Food $food): array
    {
        return [
            'id' => $food->getId(),
            'source' => $food->getSource()->value,
            'name' => $food->getName(),
            'barcode' => $food->getBarcode(),
            'offId' => $food->getOffId(),
            'kcalPer100' => $food->getKcalPer100(),
            'proteinPer100' => $food->getProteinPer100(),
            'carbsPer100' => $food->getCarbsPer100(),
            'fatPer100' => $food->getFatPer100(),
            'fiberPer100' => $food->getFiberPer100(),
            'defaultUnit' => $food->getDefaultUnit()->value,
            'unitWeightGrams' => $food->getUnitWeightGrams(),
            'editable' => null !== $food->getOwnerUser(),
        ];
    }

    /**
     * Presents a not-yet-materialized Open Food Facts search hit (no local id yet).
     *
     * @param array{offId: string, barcode: string, name: string, kcalPer100: float, proteinPer100: float, carbsPer100: float, fatPer100: float, fiberPer100: ?float} $offProduct
     *
     * @return array<string, mixed>
     */
    public function presentOffProduct(array $offProduct): array
    {
        return [
            'id' => null,
            'source' => 'off',
            'name' => $offProduct['name'],
            'barcode' => $offProduct['barcode'],
            'offId' => $offProduct['offId'],
            'kcalPer100' => $offProduct['kcalPer100'],
            'proteinPer100' => $offProduct['proteinPer100'],
            'carbsPer100' => $offProduct['carbsPer100'],
            'fatPer100' => $offProduct['fatPer100'],
            'fiberPer100' => $offProduct['fiberPer100'],
            'defaultUnit' => 'g',
            'unitWeightGrams' => null,
            'editable' => false,
        ];
    }
}
