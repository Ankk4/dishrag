<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class OrchestratorApiTest extends WebTestCase
{
    use ApiBrowserTestTrait;

    public function testQueryReturnsAnswerAndCitationsShape(): void
    {
        $client = static::createClient();
        $email = 'o'.uniqid('', true).'@example.com';

        $client->request('POST', '/api/v1/auth/register', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'email' => $email,
            'password' => 'password123',
            'name' => 'Orchestrator User',
        ], JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(201);

        $client->request('POST', '/api/v1/auth/login', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'email' => $email,
            'password' => 'password123',
        ], JSON_THROW_ON_ERROR));
        $login = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $access = $login['access_token'];
        $this->applySetCookieHeaders($client);
        $this->bearer($client, $access);

        $client->request('POST', '/api/v1/orchestrator/query', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['query' => 'Hello world'], JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(200);
        $body = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('answer', $body);
        self::assertArrayHasKey('citations', $body);
        self::assertIsArray($body['citations']);
    }
}
