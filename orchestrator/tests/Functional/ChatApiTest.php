<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ChatApiTest extends WebTestCase
{
    use ApiBrowserTestTrait;

    public function testCreateSessionSendMessagePollUntilDone(): void
    {
        $client = static::createClient();
        $email = 'c'.uniqid('', true).'@example.com';

        $client->request('POST', '/api/v1/auth/register', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'email' => $email,
            'password' => 'password123',
            'name' => 'Chat User',
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

        $client->request('POST', '/api/v1/chat/sessions', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['title' => 'Hi'], JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(201);
        $session = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $sessionId = $session['id'];

        $client->request('POST', '/api/v1/chat/sessions/'.$sessionId.'/messages', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['content' => 'What is 2+2?'], JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(202);
        $send = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $assistantId = $send['assistant_message_id'];

        $deadline = microtime(true) + 5.0;
        $status = 'processing';
        while (microtime(true) < $deadline && 'processing' === $status) {
            $client->request('GET', '/api/v1/chat/messages/'.$assistantId.'/status');
            $st = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
            $status = $st['status'];
            if ('processing' === $status) {
                usleep(50_000);
            }
        }

        self::assertSame('done', $status);
        self::assertNotEmpty($st['content'] ?? null);
    }
}
