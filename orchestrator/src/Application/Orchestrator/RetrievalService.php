<?php

declare(strict_types=1);

namespace App\Application\Orchestrator;

use App\Repository\ChunkRepository;
use Symfony\Component\Uid\Uuid;

final class RetrievalService
{
    private const DISTANCE_THRESHOLD = 0.35;

    public function __construct(
        private readonly ChunkRepository $chunkRepository,
    ) {
    }

    /**
     * @return list<array{id: string, document_id: string, text_content: string, score: float, metadata: array}>
     */
    public function retrieve(
        array $queryEmbedding,
        Uuid $ownerUserId,
        int $topK,
        ?string $docType = null,
    ): array {
        $rows = $this->chunkRepository->searchSimilar($queryEmbedding, $ownerUserId, $topK * 2, $docType);
        $filtered = [];
        foreach ($rows as $row) {
            if ($row['distance'] > self::DISTANCE_THRESHOLD) {
                continue;
            }
            $filtered[] = [
                'id' => $row['id'],
                'document_id' => $row['document_id'],
                'text_content' => $row['text_content'],
                'score' => 1.0 - (float) $row['distance'],
                'metadata' => $row['metadata'],
            ];
            if (\count($filtered) >= $topK) {
                break;
            }
        }

        return $filtered;
    }
}
