<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

final class JwtTokenService
{
    public function __construct(
        private readonly string $secret,
        private readonly int $ttlSeconds,
    ) {
        if (\strlen($this->secret) < 32) {
            throw new \InvalidArgumentException('JWT_SECRET must be at least 32 characters.');
        }
    }

    public function createAccessToken(User $user): string
    {
        $now = time();
        $payload = [
            'sub' => $user->getId()->toRfc4122(),
            'iat' => $now,
            'exp' => $now + $this->ttlSeconds,
        ];

        return JWT::encode($payload, $this->secret, 'HS256');
    }

    /**
     * @return array{sub: string, iat: int, exp: int}
     */
    public function decode(string $jwt): array
    {
        $decoded = JWT::decode($jwt, new Key($this->secret, 'HS256'));

        return (array) $decoded;
    }
}
