<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Application\Chat\SendMessageService;
use App\DTO\Chat\SendMessageDto;
use App\Entity\User;
use App\Http\ApiJson;
use App\Repository\ChatMessageRepository;
use App\Repository\ChatSessionRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\RateLimiter\RateLimiterFactory;

#[Route('/api/v1/chat')]
#[IsGranted('ROLE_USER')]
final class ChatMessageController
{
    public function __construct(
        private readonly SendMessageService $sendMessageService,
        private readonly ChatSessionRepository $chatSessionRepository,
        private readonly ChatMessageRepository $chatMessageRepository,
        #[Autowire(service: 'limiter.chat_send')]
        private readonly RateLimiterFactory $chatSendLimiter,
    ) {
    }

    #[Route('/sessions/{sessionId}/messages', name: 'api_chat_messages_send', methods: ['POST'])]
    public function send(
        Request $request,
        #[CurrentUser] User $user,
        string $sessionId,
        #[MapRequestPayload] SendMessageDto $dto,
    ): JsonResponse {
        $limiter = $this->chatSendLimiter->create('chat_'.$user->getUserIdentifier());
        if (false === $limiter->consume()->isAccepted()) {
            return ApiJson::error($request, 'rate_limit', 'Too many messages.', Response::HTTP_TOO_MANY_REQUESTS);
        }

        $sessionUuid = Uuid::fromString($sessionId);

        $ids = $this->sendMessageService->send(
            $sessionUuid,
            $user,
            $dto->content,
            $dto->clientMessageId,
        );

        return ApiJson::ok([
            'user_message_id' => $ids['user_message_id']->toRfc4122(),
            'assistant_message_id' => $ids['assistant_message_id']->toRfc4122(),
            'status' => 'processing',
        ], Response::HTTP_ACCEPTED);
    }

    #[Route('/sessions/{sessionId}/messages', name: 'api_chat_messages_list', methods: ['GET'])]
    public function list(Request $request, #[CurrentUser] User $user, string $sessionId): JsonResponse
    {
        $session = $this->chatSessionRepository->findOwned(Uuid::fromString($sessionId), $user);
        if (null === $session) {
            return ApiJson::error($request, 'not_found', 'Chat session not found.', Response::HTTP_NOT_FOUND);
        }

        $limit = min(100, max(1, (int) $request->query->get('limit', 50)));
        $before = $request->query->get('before');
        $beforeId = \is_string($before) && '' !== $before ? Uuid::fromString($before) : null;
        $messages = $this->chatMessageRepository->listForSession($session, $limit, $beforeId);

        return ApiJson::ok([
            'items' => array_map(fn ($m) => $this->serializeMessage($m), $messages),
        ]);
    }

    #[Route('/messages/{messageId}/status', name: 'api_chat_message_status', methods: ['GET'])]
    public function status(Request $request, #[CurrentUser] User $user, string $messageId): JsonResponse
    {
        $msg = $this->chatMessageRepository->findOwnedByUser(Uuid::fromString($messageId), $user);
        if (null === $msg) {
            return ApiJson::error($request, 'not_found', 'Message not found.', Response::HTTP_NOT_FOUND);
        }

        $error = null;
        if (\App\Entity\ChatMessage::STATUS_FAILED === $msg->getStatus()) {
            $error = $msg->getMetadata()['error'] ?? 'Generation failed.';
        }

        return ApiJson::ok([
            'id' => $msg->getId()->toRfc4122(),
            'status' => $msg->getStatus(),
            'content' => \App\Entity\ChatMessage::STATUS_DONE === $msg->getStatus() ? $msg->getContent() : null,
            'error' => $error,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeMessage(\App\Entity\ChatMessage $m): array
    {
        return [
            'id' => $m->getId()->toRfc4122(),
            'role' => $m->getRole(),
            'content' => $m->getContent(),
            'status' => $m->getStatus(),
            'created_at' => $m->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'metadata' => $m->getMetadata(),
        ];
    }
}
