<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AuthApiTest extends WebTestCase
{
    use ApiBrowserTestTrait;

    public function testRegisterLoginMeRefreshLogout(): void
    {
        $client = static::createClient();
        $email = 'u'.uniqid('', true).'@example.com';

        $client->request('POST', '/api/v1/auth/register', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'email' => $email,
            'password' => 'password123',
            'name' => 'Test User',
        ], JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(201);

        $client->request('POST', '/api/v1/auth/login', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'email' => $email,
            'password' => 'password123',
        ], JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(200);
        $login = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('access_token', $login);
        $access = $login['access_token'];

        $this->applySetCookieHeaders($client);
        $this->bearer($client, $access);

        $client->request('GET', '/api/v1/auth/me');
        self::assertResponseStatusCodeSame(200);
        $me = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame($email, $me['email'] ?? null);

        $client->request('POST', '/api/v1/auth/refresh');
        self::assertResponseStatusCodeSame(200);
        $refresh = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('access_token', $refresh);

        $this->applySetCookieHeaders($client);
        $this->bearer($client, $refresh['access_token']);

        $client->request('POST', '/api/v1/auth/logout', [], [], [], '{}');
        self::assertResponseStatusCodeSame(204);
    }
}
