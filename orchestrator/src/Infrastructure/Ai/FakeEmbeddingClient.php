<?php

declare(strict_types=1);

namespace App\Infrastructure\Ai;

/**
 * Deterministic pseudo-embeddings for local/test (not semantically meaningful).
 */
final class FakeEmbeddingClient implements EmbeddingClientInterface
{
    private const DIM = 1536;

    public function embed(string $text): array
    {
        $hash = hash('sha256', $text, true);
        $vec = [];
        for ($i = 0; $i < self::DIM; ++$i) {
            $b = \ord($hash[$i % \strlen($hash)]);
            $vec[] = ($b - 127.5) / 128.0;
        }

        return $this->l2Normalize($vec);
    }

    /**
     * @param list<float> $v
     *
     * @return list<float>
     */
    private function l2Normalize(array $v): array
    {
        $sum = 0.0;
        foreach ($v as $x) {
            $sum += $x * $x;
        }
        $norm = sqrt($sum) ?: 1.0;
        $out = [];
        foreach ($v as $x) {
            $out[] = $x / $norm;
        }

        return $out;
    }
}
