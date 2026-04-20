<?php

declare(strict_types=1);

namespace App\Application\Chat;

use App\Entity\ChatMessage;
use App\Entity\ChatSession;
use App\Entity\User;
use App\Message\AssistantReplyMessage;
use App\Repository\ChatSessionRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

final class SendMessageService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ChatSessionRepository $chatSessionRepository,
        private readonly MessageBusInterface $messageBus,
    ) {
    }

    /**
     * @return array{user_message_id: Uuid, assistant_message_id: Uuid}
     */
    public function send(Uuid $sessionId, User $user, string $content, ?string $clientMessageId): array
    {
        $session = $this->chatSessionRepository->findOwned($sessionId, $user);
        if (null === $session) {
            throw new NotFoundHttpException('Chat session not found.');
        }

        $userMsg = new ChatMessage();
        $userMsg->setSession($session);
        $userMsg->setRole(ChatMessage::ROLE_USER);
        $userMsg->setContent($content);
        $userMsg->setStatus(ChatMessage::STATUS_DONE);
        $userMsg->setClientMessageId($clientMessageId);

        $assistant = new ChatMessage();
        $assistant->setSession($session);
        $assistant->setRole(ChatMessage::ROLE_ASSISTANT);
        $assistant->setContent('');
        $assistant->setStatus(ChatMessage::STATUS_PROCESSING);

        $this->entityManager->persist($userMsg);
        $this->entityManager->persist($assistant);

        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            throw new ConflictHttpException('Duplicate client_message_id for this session.');
        }

        $this->messageBus->dispatch(new AssistantReplyMessage(
            $assistant->getId()->toRfc4122(),
            $userMsg->getId()->toRfc4122(),
        ));

        return [
            'user_message_id' => $userMsg->getId(),
            'assistant_message_id' => $assistant->getId(),
        ];
    }
}
