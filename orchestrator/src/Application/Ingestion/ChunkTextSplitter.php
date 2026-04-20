<?php

declare(strict_types=1);

namespace App\Application\Ingestion;

/**
 * Simple length-based splitter with overlap (MVP; replace with tokenizer-aware splitter later).
 */
final class ChunkTextSplitter
{
    private const CHUNK = 1500;

    private const OVERLAP = 200;

    /**
     * @return list<string>
     */
    public function split(string $text): array
    {
        $text = trim($text);
        if ('' === $text) {
            return [];
        }

        $chunks = [];
        $len = \strlen($text);
        $pos = 0;
        while ($pos < $len) {
            $piece = substr($text, $pos, self::CHUNK);
            $chunks[] = $piece;
            if ($pos + self::CHUNK >= $len) {
                break;
            }
            $pos += self::CHUNK - self::OVERLAP;
        }

        return $chunks;
    }

    public function estimateTokenCount(string $text): int
    {
        return (int) ceil(\strlen($text) / 4);
    }
}
