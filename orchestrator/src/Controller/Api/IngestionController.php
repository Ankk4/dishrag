<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Application\Ingestion\CreateIngestionJobService;
use App\DTO\Ingestion\CreateIngestionJobDto;
use App\Entity\User;
use App\Http\ApiJson;
use App\Repository\IngestionJobRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

#[Route('/api/v1/ingestion')]
#[IsGranted('ROLE_USER')]
final class IngestionController
{
    public function __construct(
        private readonly CreateIngestionJobService $createIngestionJobService,
        private readonly IngestionJobRepository $ingestionJobRepository,
    ) {
    }

    #[Route('/jobs', name: 'api_ingestion_jobs_create', methods: ['POST'])]
    public function createJob(
        Request $request,
        #[CurrentUser] User $user,
        #[MapRequestPayload] CreateIngestionJobDto $dto,
    ): JsonResponse {
        $idem = $request->headers->get('Idempotency-Key');
        $externalKey = \is_string($idem) && '' !== trim($idem) ? trim($idem) : null;

        $job = $this->createIngestionJobService->create(
            $user,
            $dto->sourceType,
            $dto->sourceUri,
            $dto->metadata,
            $dto->content,
            $externalKey,
        );

        return ApiJson::ok([
            'job_id' => $job->getId()->toRfc4122(),
            'status' => $job->getStatus(),
        ], Response::HTTP_ACCEPTED);
    }

    #[Route('/jobs/{jobId}', name: 'api_ingestion_jobs_get', methods: ['GET'])]
    public function getJob(Request $request, #[CurrentUser] User $user, string $jobId): JsonResponse
    {
        $job = $this->ingestionJobRepository->findOwned(Uuid::fromString($jobId), $user);
        if (null === $job) {
            return ApiJson::error($request, 'not_found', 'Job not found.', Response::HTTP_NOT_FOUND);
        }

        return ApiJson::ok([
            'job_id' => $job->getId()->toRfc4122(),
            'status' => $job->getStatus(),
            'stats' => $job->getStats(),
            'error' => $job->getError(),
        ]);
    }
}
