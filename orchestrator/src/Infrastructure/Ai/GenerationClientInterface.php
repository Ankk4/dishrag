<?php

declare(strict_types=1);

namespace App\Infrastructure\Ai;

interface GenerationClientInterface
{
    /**
     * @param list<array{role: string, content: string}> $messages
     */
    public function generate(array $messages): string;
}
