<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;
use Symfony\Component\Messenger\Transport\TransportFactoryInterface;

/**
 * Provides a Messenger Doctrine transport for a given voice worker session.
 * Queue name is voice_<sessionId> to avoid collisions.
 */
final class VoiceTransportProvider
{
    private const QUEUE_PREFIX = 'voice_';
    private const DOCTRINE_DSN_TEMPLATE = 'doctrine://default?queue_name=%s';

    public function __construct(
        private readonly TransportFactoryInterface $transportFactory,
        private readonly SerializerInterface $serializer,
    ) {
    }

    public function getTransportForSession(string $sessionId): TransportInterface
    {
        $queueName = self::QUEUE_PREFIX . $sessionId;
        $dsn = sprintf(self::DOCTRINE_DSN_TEMPLATE, $queueName);

        return $this->transportFactory->createTransport($dsn, [], $this->serializer);
    }
}
