<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Http\ApiJson;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Validator\Exception\ValidationFailedException;

final class ApiExceptionSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::EXCEPTION => ['onKernelException', 10]];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if (!str_starts_with($request->getPathInfo(), '/api')) {
            return;
        }

        $throwable = $event->getThrowable();
        if ($throwable instanceof HandlerFailedException) {
            $prev = $throwable->getPrevious();
            if (null !== $prev) {
                $throwable = $prev;
            }
        }

        $validation = $this->findValidationFailed($throwable);
        if (null !== $validation) {
            $violations = $validation->getViolations();
            $details = [];
            foreach ($violations as $v) {
                $details[] = [
                    'field' => $v->getPropertyPath(),
                    'message' => $v->getMessage(),
                ];
            }
            $response = ApiJson::error($request, 'validation_error', 'Invalid request payload', Response::HTTP_UNPROCESSABLE_ENTITY, $details);
            $event->setResponse($response);

            return;
        }

        if ($throwable instanceof HttpExceptionInterface) {
            $code = match (true) {
                $throwable->getStatusCode() >= 500 => 'system_error',
                $throwable->getStatusCode() === Response::HTTP_UNAUTHORIZED => 'authentication_error',
                $throwable->getStatusCode() === Response::HTTP_FORBIDDEN => 'authorization_error',
                $throwable->getStatusCode() === Response::HTTP_NOT_FOUND => 'not_found',
                $throwable->getStatusCode() === Response::HTTP_CONFLICT => 'conflict',
                default => 'domain_error',
            };
            $response = ApiJson::error($request, $code, $throwable->getMessage(), $throwable->getStatusCode());
            $event->setResponse($response);

            return;
        }

        $message = 'An unexpected error occurred.';
        if ('test' === ($_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? '')) {
            $message = $throwable->getMessage();
        }

        $response = ApiJson::error(
            $request,
            'system_error',
            $message,
            Response::HTTP_INTERNAL_SERVER_ERROR
        );
        $event->setResponse($response);
    }

    private function findValidationFailed(\Throwable $throwable): ?ValidationFailedException
    {
        if ($throwable instanceof ValidationFailedException) {
            return $throwable;
        }

        $prev = $throwable->getPrevious();
        if ($prev instanceof ValidationFailedException) {
            return $prev;
        }

        return null;
    }
}
