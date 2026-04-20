<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Uid\Uuid;

final class RequestIdSubscriber implements EventSubscriberInterface
{
    private const HEADER = 'X-Request-Id';

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onRequest', 256],
            KernelEvents::RESPONSE => ['onResponse', -256],
        ];
    }

    public function onRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $id = $request->headers->get(self::HEADER);
        if (!\is_string($id) || '' === trim($id)) {
            $id = Uuid::v4()->toRfc4122();
        }

        $request->attributes->set('request_id', $id);
    }

    public function onResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $response = $event->getResponse();
        $response->headers->set(self::HEADER, $request->attributes->getString('request_id'));
    }
}
