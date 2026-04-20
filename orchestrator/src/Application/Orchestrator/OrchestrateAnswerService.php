<?php

declare(strict_types=1);

namespace App\Application\Orchestrator;

use App\Entity\ChatMessage;
use App\Entity\ChatSession;
use App\Entity\User;
use App\Infrastructure\Ai\EmbeddingClientInterface;
use App\Infrastructure\Ai\GenerationClientInterface;
use App\Repository\ChatMessageRepository;
use App\Repository\ChatSessionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final class OrchestrateAnswerService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ChatMessageRepository $chatMessageRepository,
        private readonly ChatSessionRepository $chatSessionRepository,
        private readonly EmbeddingClientInterface $embeddingClient,
        private readonly RetrievalService $retrievalService,
        private readonly PromptAssemblyService $promptAssemblyService,
        private readonly GenerationClientInterface $generationClient,
    ) {
    }

    public function completeAssistantReply(
        Uuid $assistantMessageId,
        Uuid $userMessageId,
    ): void {
        $assistant = $this->chatMessageRepository->find($assistantMessageId);
        $userMsg = $this->chatMessageRepository->find($userMessageId);
        if (!$assistant instanceof ChatMessage || !$userMsg instanceof ChatMessage) {
            return;
        }

        $session = $assistant->getSession();
        $user = $session->getUser();

        try {
            $question = $userMsg->getContent();
            $history = $this->chatMessageRepository->listForSession($session, 30);
            $embedding = $this->embeddingClient->embed($question);
            $docType = null;
            $chunks = $this->retrievalService->retrieve($embedding, $user->getId(), 8, $docType);
            $messages = $this->promptAssemblyService->buildMessages($history, $chunks, $question);
            $answer = $this->generationClient->generate($messages);

            $citations = [];
            foreach ($chunks as $c) {
                $citations[] = [
                    'document_id' => $c['document_id'],
                    'chunk_id' => $c['id'],
                    'score' => $c['score'],
                ];
            }

            $assistant->setContent($answer);
            $assistant->setStatus(ChatMessage::STATUS_DONE);
            $assistant->setMetadata(['citations' => $citations]);
        } catch (\Throwable $e) {
            $assistant->setStatus(ChatMessage::STATUS_FAILED);
            $assistant->setContent('');
            $assistant->setMetadata(['error' => $e->getMessage()]);
        }

        $this->entityManager->flush();
    }

    /**
     * @param array<string, mixed>|null $filters
     *
     * @return array{answer: string, citations: list<array{document_id: string, chunk_id: string, score: float}>, session_id: ?string}
     */
    public function queryAdhoc(
        User $user,
        string $query,
        ?Uuid $sessionId,
        ?array $filters,
        int $topK,
    ): array {
        $docType = null;
        if (\is_array($filters) && isset($filters['doc_type']) && \is_string($filters['doc_type'])) {
            $docType = $filters['doc_type'];
        }

        $embedding = $this->embeddingClient->embed($query);
        $chunks = $this->retrievalService->retrieve($embedding, $user->getId(), $topK, $docType);
        $history = [];
        if (null !== $sessionId) {
            $session = $this->chatSessionRepository->findOwned($sessionId, $user);
            if (null !== $session) {
                $history = $this->chatMessageRepository->listForSession($session, 20);
            }
        }

        $messages = $this->promptAssemblyService->buildMessages($history, $chunks, $query);
        $answer = $this->generationClient->generate($messages);

        $citations = [];
        foreach ($chunks as $c) {
            $citations[] = [
                'document_id' => $c['document_id'],
                'chunk_id' => $c['id'],
                'score' => $c['score'],
            ];
        }

        return [
            'answer' => $answer,
            'citations' => $citations,
            'session_id' => $sessionId?->toRfc4122(),
        ];
    }
}
