<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class IngestionApiTest extends WebTestCase
{
    use ApiBrowserTestTrait;

    public function testCreateJobAndFetchStatus(): void
    {
        $client = static::createClient();
        $email = 'i'.uniqid('', true).'@example.com';

        $client->request('POST', '/api/v1/auth/register', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'email' => $email,
            'password' => 'password123',
            'name' => 'Ingest User',
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

        $client->request('POST', '/api/v1/ingestion/jobs', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'source_type' => 'url',
            'source_uri' => 'inline://test',
            'metadata' => [],
            'content' => "RAG test document.\nSecond line about orchestration.",
        ], JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(202);
        $job = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('job_id', $job);
        $jobId = $job['job_id'];

        $deadline = microtime(true) + 10.0;
        $status = 'queued';
        while (microtime(true) < $deadline && \in_array($status, ['queued', 'processing'], true)) {
            $client->request('GET', '/api/v1/ingestion/jobs/'.$jobId);
            $j = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
            $status = $j['status'];
            if (\in_array($status, ['queued', 'processing'], true)) {
                usleep(100_000);
            }
        }

        self::assertSame('completed', $status);
    }
}
