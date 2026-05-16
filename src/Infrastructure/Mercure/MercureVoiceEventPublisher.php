<?php

declare(strict_types=1);

namespace App\Infrastructure\Mercure;

use App\Application\Port\VoiceEventPublisherPort;
use App\Domain\Event\VoiceDomainEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Serializer\SerializerInterface;
use Twig\Environment;

final readonly class MercureVoiceEventPublisher implements VoiceEventPublisherPort
{
    public function __construct(
        private HubInterface $hub,
        private SerializerInterface $serializer,
        private Environment $twig,
        private LoggerInterface $logger,
    ) {
    }

    public function publish(VoiceDomainEvent $event): void
    {
        $data = [
            'sessionId' => $event->sessionId,
            'type' => $event->type->value,
            'occurredAt' => $event->occurredAt->format(\DateTimeInterface::ATOM),
            'payload' => $event->payload,
        ];

        $json = $this->serializer->serialize($data, 'json');

        try {
            $this->hub->publish(new Update(
                topics: [
                    '/voice/dashboard',
                    '/voice/sessions/' . $event->sessionId,
                ],
                data: $json,
                private: false,
            ));

            $html = $this->twig->render('streams/session_event.stream.html.twig', [
                'event' => $event,
                'data' => $data,
            ]);
            $this->hub->publish(new Update(
                topics: ['/voice/sessions/' . $event->sessionId],
                data: $html,
                private: false,
                type: 'text/vnd.turbo-stream.html',
            ));
        } catch (\Throwable $e) {
            $this->logger->warning('Mercure publish failed (worker continues): {message}', [
                'message' => $e->getMessage(),
                'event' => $event->type->value,
                'sessionId' => $event->sessionId,
            ]);
        }
    }
}
