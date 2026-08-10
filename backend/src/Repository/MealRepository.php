<?php

namespace App\Repository;

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
}
