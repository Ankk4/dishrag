<?php

declare(strict_types=1);

namespace App\Application\Auth;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class RegisterUserService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function register(string $email, string $plainPassword, string $name): User
    {
        if (null !== $this->userRepository->findOneByEmail($email)) {
            throw new ConflictHttpException('Email already registered.');
        }

        $user = new User();
        $user->setEmail($email);
        $user->setName($name);
        $user->setPasswordHash($this->passwordHasher->hashPassword($user, $plainPassword));

        $this->entityManager->persist($user);

        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            throw new ConflictHttpException('Email already registered.');
        }

        return $user;
    }
}
