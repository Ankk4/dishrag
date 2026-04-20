<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ChatMessage;
use App\Entity\ChatSession;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<ChatMessage>
 */
class ChatMessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ChatMessage::class);
    }

    /**
     * @return list<ChatMessage>
     */
    public function listForSession(ChatSession $session, int $limit, ?Uuid $beforeId = null): array
    {
        $qb = $this->createQueryBuilder('m')
            ->andWhere('m.session = :session')
            ->setParameter('session', $session)
            ->orderBy('m.createdAt', 'DESC')
            ->setMaxResults($limit);

        if (null !== $beforeId) {
            $before = $this->find($beforeId);
            if ($before instanceof ChatMessage) {
                $qb->andWhere('m.createdAt < :t')
                    ->setParameter('t', $before->getCreatedAt());
            }
        }

        $items = $qb->getQuery()->getResult();

        return array_reverse($items);
    }

    public function findInSession(Uuid $messageId, ChatSession $session): ?ChatMessage
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.id = :id')
            ->andWhere('m.session = :session')
            ->setParameter('id', $messageId, 'uuid')
            ->setParameter('session', $session)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findOwnedByUser(Uuid $messageId, User $user): ?ChatMessage
    {
        return $this->createQueryBuilder('m')
            ->join('m.session', 's')
            ->andWhere('m.id = :id')
            ->andWhere('s.user = :user')
            ->setParameter('id', $messageId, 'uuid')
            ->setParameter('user', $user)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
