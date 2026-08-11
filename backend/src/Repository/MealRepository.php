<?php

namespace App\Repository;

use App\Entity\Food;
use App\Entity\Meal;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Meal>
 */
class MealRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Meal::class);
    }

    /**
     * @return Meal[]
     */
    public function findForOwner(User $owner): array
    {
        return $this->findBy(['ownerUser' => $owner], ['name' => 'ASC']);
    }

    /**
     * Meals that use $food as one of their ingredients (any owner — a meal
     * can only reference a food it's already allowed to log, but the search
     * for dependents needs to be global to correctly cascade an edit).
     *
     * @return Meal[]
     */
    public function findUsingFood(Food $food): array
    {
        return $this->createQueryBuilder('m')
            ->join('m.ingredients', 'mi')
            ->andWhere('mi.food = :food')
            ->setParameter('food', $food)
            ->getQuery()
            ->getResult();
    }
}
