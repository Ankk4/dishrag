<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Application\Chat\CreateSessionService;
use App\DTO\Chat\CreateSessionDto;
use App\Entity\User;
use App\Http\ApiJson;
use App\Repository\ChatSessionRepository;
use App\Entity\ChatSession;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/chat/sessions')]
#[IsGranted('ROLE_USER')]
final class ChatSessionController
{
    public function __construct(
        private readonly CreateSessionService $createSessionService,
        private readonly ChatSessionRepository $chatSessionRepository,
    ) {
    }

    #[Route('', name: 'api_chat_sessions_create', methods: ['POST'])]
    public function create(
        #[CurrentUser] User $user,
        #[MapRequestPayload] CreateSessionDto $dto,
    ): JsonResponse {
        $session = $this->createSessionService->create($user, $dto->title);

        return ApiJson::ok($this->serializeSession($session), Response::HTTP_CREATED);
    }

    #[Route('', name: 'api_chat_sessions_list', methods: ['GET'])]
    public function list(Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $limit = min(50, max(1, (int) $request->query->get('limit', 20)));
        $offset = $this->decodeCursor($request->query->getString('cursor', ''));
        $items = $this->chatSessionRepository->listForUser($user, $limit, $offset);
        $total = $this->chatSessionRepository->countForUser($user);
        $next = ($offset + $limit) < $total ? $this->encodeCursor($offset + $limit) : null;

        return ApiJson::ok([
            'items' => array_map(fn ($s) => $this->serializeSession($s), $items),
            'next_cursor' => $next,
        ]);
    }

    private function encodeCursor(int $offset): string
    {
        return base64_encode(json_encode(['o' => $offset], JSON_THROW_ON_ERROR));
    }

    private function decodeCursor(string $cursor): int
    {
        if ('' === $cursor) {
            return 0;
        }

        try {
            $json = base64_decode($cursor, true);
            if (false === $json) {
                return 0;
            }
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

            return isset($data['o']) ? (int) $data['o'] : 0;
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeSession(ChatSession $session): array
    {
        return [
            'id' => $session->getId()->toRfc4122(),
            'title' => $session->getTitle(),
            'created_at' => $session->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'updated_at' => $session->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }
}
