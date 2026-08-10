<?php

namespace App\Repository;

use App\Entity\Enum\FoodSource;
use App\Entity\Food;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Food>
 */
class FoodRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Food::class);
    }

    public function findOneByOffId(string $offId): ?Food
    {
        return $this->findOneBy(['source' => FoodSource::OpenFoodFacts, 'offId' => $offId]);
    }

    public function findOneByBarcode(string $barcode): ?Food
    {
        return $this->findOneBy(['barcode' => $barcode]);
    }

    /**
     * @return Food[]
     */
    public function searchLocal(string $query, ?User $owner, int $limit = 20): array
    {
        $qb = $this->createQueryBuilder('f')
            ->andWhere('LOWER(f.name) LIKE :query')
            ->setParameter('query', '%'.mb_strtolower($query).'%')
            ->andWhere('f.source NOT IN (:privateSources) OR f.ownerUser = :owner')
            ->setParameter('privateSources', [FoodSource::Custom, FoodSource::Meal])
            ->setParameter('owner', $owner)
            ->setMaxResults($limit);

        return $qb->getQuery()->getResult();
    }

    /**
     * @return Food[]
     */
    public function findCustomForOwner(User $owner): array
    {
        return $this->findBy(['source' => FoodSource::Custom, 'ownerUser' => $owner], ['name' => 'ASC']);
    }
}
