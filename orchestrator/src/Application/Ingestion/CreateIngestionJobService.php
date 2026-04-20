<?php

declare(strict_types=1);

namespace App\Application\Ingestion;

use App\Entity\IngestionJob;
use App\Entity\User;
use App\Message\ProcessIngestionJobMessage;
use App\Repository\IngestionJobRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Messenger\MessageBusInterface;

final class CreateIngestionJobService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly IngestionJobRepository $ingestionJobRepository,
        private readonly MessageBusInterface $messageBus,
    ) {
    }

    public function create(
        User $owner,
        string $sourceType,
        string $sourceUri,
        array $metadata,
        ?string $inlineContent,
        ?string $externalKey,
    ): IngestionJob {
        if (null !== $externalKey && '' !== $externalKey) {
            $existing = $this->ingestionJobRepository->findOneByOwnerAndExternalKey($owner->getId(), $externalKey);
            if (null !== $existing) {
                throw new ConflictHttpException('Duplicate idempotency key for ingestion.');
            }
        }

        $job = new IngestionJob();
        $job->setOwnerUser($owner);
        $job->setSourceType($sourceType);
        $job->setSourceUri($sourceUri);
        $job->setStatus(IngestionJob::STATUS_QUEUED);
        $job->setExternalKey($externalKey);
        $job->setStats([
            'inline_content' => $inlineContent,
            'ingestion_metadata' => $metadata,
        ]);

        $this->entityManager->persist($job);
        $this->entityManager->flush();

        $this->messageBus->dispatch(new ProcessIngestionJobMessage($job->getId()->toRfc4122()));

        return $job;
    }
}
