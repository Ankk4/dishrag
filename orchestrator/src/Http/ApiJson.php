<?php

declare(strict_types=1);

namespace App\Http;

use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final class ApiJson
{
    /**
     * @param array<string, mixed> $body
     */
    public static function ok(array $body, int $status = 200): JsonResponse
    {
        return new JsonResponse($body, $status);
    }

    /**
     * @param array<string, mixed>|null $details
     */
    public static function error(
        Request $request,
        string $code,
        string $message,
        int $status,
        ?array $details = null,
    ): JsonResponse {
        $payload = [
            'error' => [
                'code' => $code,
                'message' => $message,
                'request_id' => $request->attributes->getString('request_id'),
            ],
        ];
        if (null !== $details) {
            $payload['error']['details'] = $details;
        }

        return new JsonResponse($payload, $status);
    }

    public static function refreshCookie(string $name, string $value, int $maxAgeSeconds, Request $request): Cookie
    {
        return Cookie::create(
            $name,
            $value,
            time() + $maxAgeSeconds,
            '/',
            null,
            $request->isSecure(),
            true,
            false,
            Cookie::SAMESITE_LAX
        );
    }

    public static function expireRefreshCookie(string $name, Request $request): Cookie
    {
        return Cookie::create(
            $name,
            '',
            time() - 3600,
            '/',
            null,
            $request->isSecure(),
            true,
            false,
            Cookie::SAMESITE_LAX
        );
    }
}
