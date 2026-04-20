<?php

declare(strict_types=1);

namespace App\DTO\Auth;

use Symfony\Component\Validator\Constraints as Assert;

final class RegisterDto
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Email]
        public readonly string $email = '',
        #[Assert\NotBlank]
        #[Assert\Length(min: 8, max: 128)]
        public readonly string $password = '',
        #[Assert\NotBlank]
        #[Assert\Length(max: 120)]
        public readonly string $name = '',
    ) {
    }
}
