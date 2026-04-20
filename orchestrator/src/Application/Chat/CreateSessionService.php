<?php

declare(strict_types=1);

namespace App\Application\Chat;

use App\Entity\ChatSession;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

final class CreateSessionService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function create(User $user, ?string $title): ChatSession
    {
        $session = new ChatSession();
        $session->setUser($user);
        if (null !== $title && '' !== trim($title)) {
            $session->setTitle(trim($title));
        }
        $this->entityManager->persist($session);
        $this->entityManager->flush();

        return $session;
    }
}
