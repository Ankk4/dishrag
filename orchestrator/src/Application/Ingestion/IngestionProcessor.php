<?php

declare(strict_types=1);

namespace App\Application\Ingestion;

use App\Entity\Chunk;
use App\Entity\Document;
use App\Entity\IngestionJob;
use App\Infrastructure\Ai\EmbeddingClientInterface;
use App\Repository\DocumentRepository;
use App\Repository\IngestionJobRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class IngestionProcessor
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly IngestionJobRepository $ingestionJobRepository,
        private readonly DocumentRepository $documentRepository,
        private readonly ChunkTextSplitter $chunkTextSplitter,
        private readonly EmbeddingClientInterface $embeddingClient,
        private readonly ?HttpClientInterface $httpClient = null,
    ) {
    }

    public function process(Uuid $jobId): void
    {
        $job = $this->ingestionJobRepository->find($jobId);
        if (!$job instanceof IngestionJob) {
            return;
        }

        if (IngestionJob::STATUS_COMPLETED === $job->getStatus()) {
            return;
        }

        $job->setStatus(IngestionJob::STATUS_PROCESSING);
        $job->setStartedAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        try {
            $text = $this->resolveText($job);
            $sha = hash('sha256', $text);
            $document = $this->documentRepository->findOneByContentSha256($sha);
            if (null === $document) {
                $document = new Document();
                $document->setOwnerUser($job->getOwnerUser());
                $document->setSourceType($job->getSourceType());
                $document->setSourceUri($job->getSourceUri());
                $document->setDocType('generic');
                $document->setTitle($job->getSourceUri());
                $document->setContent($text);
                $document->setContentSha256($sha);
                $meta = $job->getStats()['ingestion_metadata'] ?? [];
                $document->setMetadata(\is_array($meta) ? $meta : []);
                $this->entityManager->persist($document);
                $this->entityManager->flush();
            }

            $chunkCount = (int) $this->entityManager->getRepository(Chunk::class)->count(['document' => $document]);
            if (0 === $chunkCount) {
                $parts = $this->chunkTextSplitter->split($document->getContent());
                foreach ($parts as $i => $part) {
                    $chunk = new Chunk();
                    $chunk->setDocument($document);
                    $chunk->setOwnerUser($job->getOwnerUser());
                    $chunk->setChunkIndex($i);
                    $chunk->setTextContent($part);
                    $chunk->setTokenCount($this->chunkTextSplitter->estimateTokenCount($part));
                    $chunk->setEmbedding($this->embeddingClient->embed($part));
                    $chunk->setMetadata([
                        'doc_type' => $document->getDocType(),
                        'source_type' => $document->getSourceType(),
                        'source_uri' => $document->getSourceUri(),
                    ]);
                    $this->entityManager->persist($chunk);
                }
                $this->entityManager->flush();
                $chunkCount = \count($parts);
            }

            $job->setStatus(IngestionJob::STATUS_COMPLETED);
            $prev = $job->getStats();
            $job->setStats(array_merge(\is_array($prev) ? $prev : [], [
                'documents' => 1,
                'chunks' => $chunkCount,
            ]));
            $job->setCompletedAt(new \DateTimeImmutable());
        } catch (\Throwable $e) {
            $job->setStatus(IngestionJob::STATUS_FAILED);
            $prev = $job->getStats();
            $job->setStats(array_merge(\is_array($prev) ? $prev : [], [
                'error' => $e->getMessage(),
            ]));
            $job->setError($e->getMessage());
            $job->setCompletedAt(new \DateTimeImmutable());
        }

        $this->entityManager->flush();
    }

    private function resolveText(IngestionJob $job): string
    {
        $stats = $job->getStats();
        if (isset($stats['inline_content']) && \is_string($stats['inline_content']) && '' !== trim($stats['inline_content'])) {
            return trim($stats['inline_content']);
        }

        if ('url' === $job->getSourceType()) {
            $client = $this->httpClient ?? HttpClient::create();
            $response = $client->request('GET', $job->getSourceUri(), [
                'timeout' => 15,
                'headers' => [
                    'User-Agent' => 'DishragOrchestrator/1.0',
                ],
            ]);
            if ($response->getStatusCode() >= 400) {
                throw new \RuntimeException('Failed to fetch URL: HTTP '.$response->getStatusCode());
            }

            return strip_tags((string) $response->getContent());
        }

        throw new \RuntimeException('No ingestible content provided for this job.');
    }
}
