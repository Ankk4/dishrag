<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ChunkRepository;
use Pgvector\Vector;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: ChunkRepository::class)]
#[ORM\Table(name: 'chunks')]
#[ORM\UniqueConstraint(name: 'uq_document_chunk_idx', columns: ['document_id', 'chunk_index'])]
class Chunk
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Document::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Document $document;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $ownerUser = null;

    #[ORM\Column]
    private int $chunkIndex = 0;

    #[ORM\Column(type: Types::TEXT)]
    private string $textContent = '';

    #[ORM\Column]
    private int $tokenCount = 0;

    #[ORM\Column(type: 'vector', length: 1536)]
    private ?Vector $embedding = null;

    #[ORM\Column(type: Types::JSON)]
    private array $metadata = [];

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->id = Uuid::v4();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getDocument(): Document
    {
        return $this->document;
    }

    public function setDocument(Document $document): static
    {
        $this->document = $document;

        return $this;
    }

    public function getOwnerUser(): ?User
    {
        return $this->ownerUser;
    }

    public function setOwnerUser(?User $ownerUser): static
    {
        $this->ownerUser = $ownerUser;

        return $this;
    }

    public function getChunkIndex(): int
    {
        return $this->chunkIndex;
    }

    public function setChunkIndex(int $chunkIndex): static
    {
        $this->chunkIndex = $chunkIndex;

        return $this;
    }

    public function getTextContent(): string
    {
        return $this->textContent;
    }

    public function setTextContent(string $textContent): static
    {
        $this->textContent = $textContent;

        return $this;
    }

    public function getTokenCount(): int
    {
        return $this->tokenCount;
    }

    public function setTokenCount(int $tokenCount): static
    {
        $this->tokenCount = $tokenCount;

        return $this;
    }

    /**
     * @return list<float>|null
     */
    /**
     * @return list<float>|null
     */
    public function getEmbedding(): ?array
    {
        return $this->embedding?->toArray();
    }

    /**
     * @param list<float>|null $embedding
     */
    public function setEmbedding(?array $embedding): static
    {
        $this->embedding = null === $embedding ? null : new Vector($embedding);

        return $this;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function setMetadata(array $metadata): static
    {
        $this->metadata = $metadata;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
