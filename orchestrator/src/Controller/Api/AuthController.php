<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Application\Auth\LoginService;
use App\Application\Auth\RefreshTokenService;
use App\Application\Auth\RegisterUserService;
use App\DTO\Auth\LoginDto;
use App\DTO\Auth\RegisterDto;
use App\Http\ApiJson;
use App\Security\JwtTokenService;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\RateLimiter\RateLimiterFactory;

#[Route('/api/v1/auth')]
final class AuthController
{
    public function __construct(
        private readonly RegisterUserService $registerUserService,
        private readonly LoginService $loginService,
        private readonly JwtTokenService $jwtTokenService,
        private readonly RefreshTokenService $refreshTokenService,
        #[Autowire('%app.refresh_cookie_name%')]
        private readonly string $refreshCookieName,
        #[Autowire('%env(int:REFRESH_TOKEN_TTL)%')]
        private readonly int $refreshTtlSeconds,
        #[Autowire('%env(int:JWT_TTL)%')]
        private readonly int $jwtTtlSeconds,
        #[Autowire(service: 'limiter.auth_login')]
        private readonly RateLimiterFactory $authLoginLimiter,
    ) {
    }

    #[Route('/register', name: 'api_auth_register', methods: ['POST'])]
    public function register(#[MapRequestPayload] RegisterDto $dto): JsonResponse
    {
        $user = $this->registerUserService->register($dto->email, $dto->password, $dto->name);

        return ApiJson::ok([
            'user' => $this->serializeUser($user),
        ], Response::HTTP_CREATED);
    }

    #[Route('/login', name: 'api_auth_login', methods: ['POST'])]
    public function login(
        #[MapRequestPayload] LoginDto $dto,
        Request $request,
    ): JsonResponse {
        $limiter = $this->authLoginLimiter->create('login_'.$request->getClientIp());
        if (false === $limiter->consume()->isAccepted()) {
            return ApiJson::error($request, 'rate_limit', 'Too many login attempts.', Response::HTTP_TOO_MANY_REQUESTS);
        }

        $user = $this->loginService->authenticate($dto->email, $dto->password);
        $access = $this->jwtTokenService->createAccessToken($user);
        $refresh = $this->refreshTokenService->issue($user, $request);

        $response = ApiJson::ok([
            'access_token' => $access,
            'expires_in' => $this->jwtTtlSeconds,
            'token_type' => 'Bearer',
            'user' => $this->serializeUser($user),
        ]);
        $response->headers->setCookie(ApiJson::refreshCookie($this->refreshCookieName, $refresh['token'], $this->refreshTtlSeconds, $request));

        return $response;
    }

    #[Route('/refresh', name: 'api_auth_refresh', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function refresh(Request $request, #[CurrentUser] \App\Entity\User $user): JsonResponse
    {
        $raw = $request->cookies->get($this->refreshCookieName, '');
        if (!\is_string($raw) || '' === $raw) {
            return ApiJson::error($request, 'authentication_error', 'Missing refresh token.', Response::HTTP_UNAUTHORIZED);
        }

        $rotated = $this->refreshTokenService->rotate($user, $request, $raw);
        $access = $this->jwtTokenService->createAccessToken($user);

        $response = ApiJson::ok([
            'access_token' => $access,
            'expires_in' => $this->jwtTtlSeconds,
            'token_type' => 'Bearer',
        ]);
        $response->headers->setCookie(ApiJson::refreshCookie($this->refreshCookieName, $rotated['refreshToken'], $this->refreshTtlSeconds, $request));

        return $response;
    }

    #[Route('/logout', name: 'api_auth_logout', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function logout(Request $request, #[CurrentUser] \App\Entity\User $user): Response
    {
        $payload = json_decode($request->getContent() ?: '{}', true);
        $allDevices = \is_array($payload) && !empty($payload['all_devices']);

        if ($allDevices) {
            $this->refreshTokenService->revokeAllForUser($user);
        } else {
            $raw = $request->cookies->get($this->refreshCookieName, '');
            $this->refreshTokenService->revokeByRawToken(\is_string($raw) ? $raw : null);
        }

        $response = new Response('', Response::HTTP_NO_CONTENT);
        $response->headers->setCookie(ApiJson::expireRefreshCookie($this->refreshCookieName, $request));

        return $response;
    }

    #[Route('/me', name: 'api_auth_me', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function me(#[CurrentUser] \App\Entity\User $user): JsonResponse
    {
        return ApiJson::ok($this->serializeUser($user));
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeUser(\App\Entity\User $user): array
    {
        return [
            'id' => $user->getId()->toRfc4122(),
            'email' => $user->getEmail(),
            'name' => $user->getName(),
            'created_at' => $user->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }

}
