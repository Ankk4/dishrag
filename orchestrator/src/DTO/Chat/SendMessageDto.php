<?php

declare(strict_types=1);

namespace App\DTO\Chat;

use Symfony\Component\Validator\Constraints as Assert;

final class SendMessageDto
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(min: 1, max: 8000)]
        public readonly string $content = '',
        #[Assert\Length(max: 100)]
        public readonly ?string $clientMessageId = null,
    ) {
    }
}
