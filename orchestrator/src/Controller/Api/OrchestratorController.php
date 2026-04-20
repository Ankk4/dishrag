<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Application\Orchestrator\OrchestrateAnswerService;
use App\DTO\Orchestrator\QueryDto;
use App\Entity\User;
use App\Http\ApiJson;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

#[Route('/api/v1/orchestrator')]
#[IsGranted('ROLE_USER')]
final class OrchestratorController
{
    public function __construct(
        private readonly OrchestrateAnswerService $orchestrateAnswerService,
    ) {
    }

    #[Route('/query', name: 'api_orchestrator_query', methods: ['POST'])]
    public function query(
        Request $request,
        #[CurrentUser] User $user,
        #[MapRequestPayload] QueryDto $dto,
    ): JsonResponse {
        $sessionId = null;
        if (null !== $dto->sessionId && '' !== $dto->sessionId) {
            try {
                $sessionId = Uuid::fromString($dto->sessionId);
            } catch (\Throwable) {
                return ApiJson::error($request, 'validation_error', 'Invalid session_id.', 422);
            }
        }

        $result = $this->orchestrateAnswerService->queryAdhoc(
            $user,
            $dto->query,
            $sessionId,
            $dto->filters,
            $dto->topK,
        );

        return ApiJson::ok($result);
    }
}
