<?php

declare(strict_types=1);

namespace App\Message;

final readonly class AssistantReplyMessage
{
    public function __construct(
        public string $assistantMessageId,
        public string $userMessageId,
    ) {
    }
}
