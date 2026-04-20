<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\IngestionJob;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<IngestionJob>
 */
class IngestionJobRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, IngestionJob::class);
    }

    public function findOneByOwnerAndExternalKey(Uuid $ownerId, string $externalKey): ?IngestionJob
    {
        return $this->createQueryBuilder('j')
            ->join('j.ownerUser', 'u')
            ->andWhere('u.id = :uid')
            ->andWhere('j.externalKey = :ek')
            ->setParameter('uid', $ownerId, 'uuid')
            ->setParameter('ek', $externalKey)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findOwned(Uuid $jobId, User $user): ?IngestionJob
    {
        return $this->createQueryBuilder('j')
            ->andWhere('j.id = :id')
            ->andWhere('j.ownerUser = :user')
            ->setParameter('id', $jobId, 'uuid')
            ->setParameter('user', $user)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
