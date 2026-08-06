<?php

namespace App\Service;

use App\Entity\Enum\FoodSource;
use App\Entity\Enum\FoodUnit;
use App\Entity\Food;
use App\OpenFoodFacts\OpenFoodFactsClient;
use App\Repository\FoodRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Turns an Open Food Facts product into a local, persisted Food row the
 * first time a user actually logs it (search results are never persisted
 * on their own).
 */
final class FoodMaterializer
{
    public function __construct(
        private readonly FoodRepository $foodRepository,
        private readonly OpenFoodFactsClient $offClient,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function materialize(string $offCode): ?Food
    {
        $existing = $this->foodRepository->findOneByOffId($offCode);
        if (null !== $existing) {
            return $existing;
        }

        $product = $this->offClient->getByBarcode($offCode);
        if (null === $product) {
            return null;
        }

        $food = new Food();
        $food->setName($product['name']);
        $food->setSource(FoodSource::OpenFoodFacts);
        $food->setOffId($product['offId']);
        $food->setBarcode($product['barcode']);
        $food->setKcalPer100($product['kcalPer100']);
        $food->setProteinPer100($product['proteinPer100']);
        $food->setCarbsPer100($product['carbsPer100']);
        $food->setFatPer100($product['fatPer100']);
        $food->setFiberPer100($product['fiberPer100']);
        $food->setDefaultUnit(FoodUnit::Gram);

        $this->em->persist($food);
        $this->em->flush();

        return $food;
    }
}
