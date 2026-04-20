<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Chunk;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ParameterType;
use Doctrine\Persistence\ManagerRegistry;
use Pgvector\Vector;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<Chunk>
 */
class ChunkRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Chunk::class);
    }

    /**
     * @param list<float> $queryEmbedding
     *
     * @return list<array{id: string, document_id: string, text_content: string, distance: float, metadata: array}>
     */
    public function searchSimilar(
        array $queryEmbedding,
        ?Uuid $ownerUserId,
        int $topK,
        ?string $docType = null,
    ): array {
        
        if (null === $ownerUserId) {
            throw new \InvalidArgumentException('ownerUserId is required for scoped retrieval.');
        }
        
        $params = ['q' => $vectorLiteral, 'owner' => $ownerUserId->toRfc4122(), 'lim' => $topK];
        $types = [ 'lim' => ParameterType::INTEGER ];

        if (null !== $docType && '' !== $docType) {
            $params['doc_type'] = $docType;
        }

        $conn = $this->getEntityManager()->getConnection();
        $vectorLiteral = (string) new Vector($queryEmbedding);

        $sql = <<<'SQL'
            SELECT c.id::text AS id,
                c.document_id::text AS document_id,
                c.text_content,
                (c.embedding <=> CAST(:q AS vector)) AS distance,
                c.metadata
            FROM chunks c
            INNER JOIN documents d ON d.id = c.document_id
            WHERE (
                c.owner_user_id = CAST(:owner AS uuid)
                OR c.owner_user_id IS NULL
            )
            SQL;

        if (null !== $docType && '' !== $docType) {
            $sql .= ' AND d.doc_type = :doc_type ';
        }

        $sql .= ' ORDER BY c.embedding <=> CAST(:q AS vector) ASC LIMIT :lim ';
        $stmt = $conn->executeQuery($sql, $params, $types);

        $rows = [];
        foreach ($stmt->iterateAssociative() as $row) {
            $meta = $row['metadata'];
            if (\is_string($meta)) {
                $decoded = json_decode($meta, true, 512, JSON_THROW_ON_ERROR);
                $meta = \is_array($decoded) ? $decoded : [];
            }
            $rows[] = [
                'id' => (string) $row['id'],
                'document_id' => (string) $row['document_id'],
                'text_content' => (string) $row['text_content'],
                'distance' => (float) $row['distance'],
                'metadata' => $meta,
            ];
        }

        return $rows;
    }
}
