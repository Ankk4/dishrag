<?php

declare(strict_types=1);

namespace App\Infrastructure\Ai;

interface EmbeddingClientInterface
{
    /**
     * @return list<float> length 1536
     */
    public function embed(string $text): array;
}
