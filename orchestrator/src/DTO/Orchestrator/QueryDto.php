<?php

declare(strict_types=1);

namespace App\DTO\Orchestrator;

use Symfony\Component\Validator\Constraints as Assert;

final class QueryDto
{
    /**
     * @param array<string, mixed>|null $filters
     */
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(min: 1, max: 8000)]
        public readonly string $query = '',
        public readonly ?string $sessionId = null,
        public readonly ?array $filters = null,
        #[Assert\Range(min: 1, max: 20)]
        public readonly int $topK = 8,
    ) {
    }
}
