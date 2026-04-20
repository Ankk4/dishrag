<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Application\Ingestion\IngestionProcessor;
use App\Message\ProcessIngestionJobMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final class ProcessIngestionJobMessageHandler
{
    public function __construct(
        private readonly IngestionProcessor $ingestionProcessor,
    ) {
    }

    public function __invoke(ProcessIngestionJobMessage $message): void
    {
        $this->ingestionProcessor->process(Uuid::fromString($message->jobId));
    }
}
