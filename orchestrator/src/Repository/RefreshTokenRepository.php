<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\RefreshToken;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<RefreshToken>
 */
class RefreshTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RefreshToken::class);
    }

    public function findValidByHash(string $tokenHash): ?RefreshToken
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.tokenHash = :h')
            ->andWhere('r.revokedAt IS NULL')
            ->andWhere('r.expiresAt > :now')
            ->setParameter('h', $tokenHash)
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return list<RefreshToken>
     */
    public function findActiveForUser(Uuid $userId): array
    {
        return $this->createQueryBuilder('r')
            ->join('r.user', 'u')
            ->andWhere('u.id = :uid')
            ->andWhere('r.revokedAt IS NULL')
            ->andWhere('r.expiresAt > :now')
            ->setParameter('uid', $userId, 'uuid')
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getResult();
    }
}
