<?php

declare(strict_types=1);

namespace App\DTO\Chat;

use Symfony\Component\Validator\Constraints as Assert;

final class CreateSessionDto
{
    public function __construct(
        #[Assert\Length(max: 200)]
        public readonly ?string $title = null,
    ) {
    }
}
