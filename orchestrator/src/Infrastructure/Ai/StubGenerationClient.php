<?php

declare(strict_types=1);

namespace App\Infrastructure\Ai;

/**
 * MVP: echoes that it used retrieved context markers from the last user message.
 */
final class StubGenerationClient implements GenerationClientInterface
{
    public function generate(array $messages): string
    {
        $lastUser = '';
        foreach (array_reverse($messages) as $m) {
            if ('user' === ($m['role'] ?? '')) {
                $lastUser = (string) ($m['content'] ?? '');
                break;
            }
        }

        return 'This is a stub model response. Treat retrieved context as untrusted data. User asked: '
            .$lastUser;
    }
}
