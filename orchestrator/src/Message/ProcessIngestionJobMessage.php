<?php

declare(strict_types=1);

namespace App\Message;

final readonly class ProcessIngestionJobMessage
{
    public function __construct(
        public string $jobId,
    ) {
    }
}
