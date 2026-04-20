<?php

declare(strict_types=1);

namespace App\Security;

use App\Http\ApiJson;
use App\Repository\RefreshTokenRepository;
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

final class RefreshCookieAuthenticator extends AbstractAuthenticator implements AuthenticationEntryPointInterface
{
    public function __construct(
        private readonly RefreshTokenRepository $refreshTokenRepository,
        private readonly UserRepository $userRepository,
        private readonly string $cookieName,
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
        return 'POST' === $request->getMethod()
            && '/api/v1/auth/refresh' === $request->getPathInfo();
    }

    public function authenticate(Request $request): Passport
    {
        $raw = $request->cookies->get($this->cookieName, '');
        if (!\is_string($raw) || '' === $raw) {
            throw new CustomUserMessageAuthenticationException('Missing refresh token.');
        }

        $hash = hash('sha256', $raw);
        $refresh = $this->refreshTokenRepository->findValidByHash($hash);
        if (null === $refresh) {
            throw new CustomUserMessageAuthenticationException('Invalid refresh token.');
        }

        $user = $refresh->getUser();
        $sub = $user->getId()->toRfc4122();

        return new SelfValidatingPassport(
            new UserBadge($sub, function () use ($user) {
                $fresh = $this->userRepository->find($user->getId());
                if (null === $fresh) {
                    throw new CustomUserMessageAuthenticationException('User not found.');
                }

                return $fresh;
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
