<?php

namespace App\Repository;

use App\Entity\DiaryEntry;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DiaryEntry>
 */
class DiaryEntryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DiaryEntry::class);
    }

    /**
     * @return DiaryEntry[]
     */
    public function findForUserOnDate(User $user, \DateTimeImmutable $date): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.user = :user')
            ->andWhere('d.consumedAt = :date')
            ->setParameter('user', $user)
            ->setParameter('date', $date)
            ->orderBy('d.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
