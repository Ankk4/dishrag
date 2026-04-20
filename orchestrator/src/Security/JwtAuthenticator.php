<?php

declare(strict_types=1);

namespace App\Security;

use App\Http\ApiJson;
use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;
use Symfony\Component\Uid\Uuid;

final class JwtAuthenticator extends AbstractAuthenticator implements AuthenticationEntryPointInterface
{
    public function __construct(
        private readonly JwtTokenService $jwtTokenService,
        private readonly UserRepository $userRepository,
    ) {
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        $msg = $authException instanceof AuthenticationException
            ? strtr($authException->getMessageKey(), $authException->getMessageData())
            : 'Authentication required.';

        return ApiJson::error($request, 'authentication_error', $msg, Response::HTTP_UNAUTHORIZED);
    }

    public function supports(Request $request): ?bool
    {
        if (!str_starts_with($request->getPathInfo(), '/api/')) {
            return false;
        }

        if ('/api/v1/auth/refresh' === $request->getPathInfo()) {
            return false;
        }

        if (str_starts_with($request->getPathInfo(), '/api/v1/auth/')) {
            $public = ['/api/v1/auth/register', '/api/v1/auth/login'];

            return !\in_array($request->getPathInfo(), $public, true);
        }

        return true;
    }

    public function authenticate(Request $request): Passport
    {
        $auth = $request->headers->get('Authorization', '');
        if (!str_starts_with($auth, 'Bearer ')) {
            throw new CustomUserMessageAuthenticationException('Missing Bearer token.');
        }

        $token = trim(substr($auth, 7));
        if ('' === $token) {
            throw new CustomUserMessageAuthenticationException('Empty Bearer token.');
        }

        try {
            $payload = $this->jwtTokenService->decode($token);
        } catch (\Throwable) {
            throw new CustomUserMessageAuthenticationException('Invalid or expired token.');
        }

        $sub = $payload['sub'] ?? null;
        if (!\is_string($sub) || '' === $sub) {
            throw new CustomUserMessageAuthenticationException('Invalid token subject.');
        }

        try {
            $uuid = Uuid::fromString($sub);
        } catch (\Throwable) {
            throw new CustomUserMessageAuthenticationException('Invalid token subject.');
        }

        return new SelfValidatingPassport(
            new UserBadge($sub, function () use ($uuid) {
                $user = $this->userRepository->find($uuid);
                if (null === $user) {
                    throw new CustomUserMessageAuthenticationException('User not found.');
                }

                return $user;
            })
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return $this->start($request, $exception);
    }
}
