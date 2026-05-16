<?php

declare(strict_types=1);

namespace App\Infrastructure\Messenger;

use App\Application\Port\VoiceControlPort;
use App\Enum\VoiceControlType;
use App\Message\VoiceControlMessage;
use App\Repository\VoiceWorkerSessionRepository;
use App\Service\VoiceTransportProvider;
use Symfony\Component\Messenger\Envelope;

final readonly class MessengerVoiceControlAdapter implements VoiceControlPort
{
    public function __construct(
        private VoiceTransportProvider $transportProvider,
        private VoiceWorkerSessionRepository $sessionRepository,
    ) {
    }

    public function send(VoiceControlType $type, ?string $sessionId = null): array
    {
        $sessions = $sessionId !== null
            ? [['id' => $sessionId]]
            : array_map(
                static fn ($s): array => ['id' => $s->getId()],
                $this->sessionRepository->findActiveSessions(new \DateTimeImmutable('-30 seconds')),
            );

        if ($sessions === []) {
            return [];
        }

        $message = new VoiceControlMessage($type, new \DateTimeImmutable());
        $envelope = Envelope::wrap($message);
        $sent = [];

        foreach ($sessions as $session) {
            $id = $session['id'];
            try {
                $this->transportProvider->getTransportForSession($id)->send($envelope);
                $sent[] = $id;
            } catch (\Throwable) {
                // skip failed transports
            }
        }

        return $sent;
    }
}
