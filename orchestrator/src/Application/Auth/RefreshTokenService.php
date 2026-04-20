<?php

declare(strict_types=1);

namespace App\Application\Auth;

use App\Entity\RefreshToken;
use App\Entity\User;
use App\Repository\RefreshTokenRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

final class RefreshTokenService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly RefreshTokenRepository $refreshTokenRepository,
        private readonly int $refreshTtlSeconds,
    ) {
    }

    /**
     * @return array{token: string, entity: RefreshToken}
     */
    public function issue(User $user, Request $request): array
    {
        $pair = $this->createToken($user, $request);
        $this->entityManager->flush();

        return $pair;
    }

    /**
     * @return array{refreshToken: string, entity: RefreshToken}
     */
    public function rotate(User $user, Request $request, string $rawCurrent): array
    {
        $hash = hash('sha256', $rawCurrent);
        $current = $this->refreshTokenRepository->findValidByHash($hash);
        if (null === $current) {
            throw new \RuntimeException('Invalid refresh token.');
        }

        if (!$current->getUser()->getId()->equals($user->getId())) {
            throw new \RuntimeException('Refresh token does not match authenticated user.');
        }

        $current->revoke();
        $new = $this->createToken($user, $request);
        $current->setReplacedByJti($new['entity']->getJti());
        $this->entityManager->flush();

        return ['refreshToken' => $new['token'], 'entity' => $new['entity']];
    }

    /**
     * @return array{token: string, entity: RefreshToken}
     */
    private function createToken(User $user, Request $request): array
    {
        $raw = bin2hex(random_bytes(32));
        $entity = new RefreshToken();
        $entity->setUser($user);
        $entity->setTokenHash(hash('sha256', $raw));
        $entity->setExpiresAt(new \DateTimeImmutable('+'.$this->refreshTtlSeconds.' seconds'));
        $entity->setIpAddress($request->getClientIp());
        $entity->setUserAgent($request->headers->get('User-Agent'));

        $this->entityManager->persist($entity);

        return ['token' => $raw, 'entity' => $entity];
    }

    public function revokeByRawToken(?string $raw): void
    {
        if (null === $raw || '' === $raw) {
            return;
        }

        $entity = $this->refreshTokenRepository->findValidByHash(hash('sha256', $raw));
        if (null !== $entity) {
            $entity->revoke();
            $this->entityManager->flush();
        }
    }

    public function revokeAllForUser(User $user): void
    {
        foreach ($this->refreshTokenRepository->findActiveForUser($user->getId()) as $t) {
            $t->revoke();
        }
        $this->entityManager->flush();
    }
}
