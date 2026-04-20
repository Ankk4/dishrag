<?php

declare(strict_types=1);

namespace App\Application\Auth;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class LoginService
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function authenticate(string $email, string $plainPassword): User
    {
        $user = $this->userRepository->findOneByEmail($email);
        if (null === $user) {
            throw new UnauthorizedHttpException('Bearer', 'Invalid credentials.');
        }

        if (!$this->passwordHasher->isPasswordValid($user, $plainPassword)) {
            throw new UnauthorizedHttpException('Bearer', 'Invalid credentials.');
        }

        return $user;
    }
}
