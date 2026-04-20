<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Application\Orchestrator\OrchestrateAnswerService;
use App\Message\AssistantReplyMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final class AssistantReplyMessageHandler
{
    public function __construct(
        private readonly OrchestrateAnswerService $orchestrateAnswerService,
    ) {
    }

    public function __invoke(AssistantReplyMessage $message): void
    {
        $this->orchestrateAnswerService->completeAssistantReply(
            Uuid::fromString($message->assistantMessageId),
            Uuid::fromString($message->userMessageId),
        );
    }
}
