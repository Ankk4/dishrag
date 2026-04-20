<?php

declare(strict_types=1);

namespace App\DTO\Ingestion;

use Symfony\Component\Validator\Constraints as Assert;

final class CreateIngestionJobDto
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Choice(choices: ['url', 'api', 'file', 'media'])]
        public readonly string $sourceType = '',
        #[Assert\NotBlank]
        public readonly string $sourceUri = '',
        public readonly array $metadata = [],
        public readonly ?string $content = null,
        public readonly ?string $mediaUri = null,
    ) {
    }
}
