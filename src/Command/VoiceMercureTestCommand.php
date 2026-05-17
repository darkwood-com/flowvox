<?php

declare(strict_types=1);

namespace App\Command;

use App\Domain\Enum\VoiceDomainEventType;
use App\Domain\Event\VoiceDomainEvent;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'voice:mercure-test',
    description: 'Publish a test event to Mercure (verify hub URL and JWT)',
)]
final class VoiceMercureTestCommand extends Command
{
    public function __construct(
        private readonly HubInterface $hub,
        private readonly SerializerInterface $serializer,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('session', 's', InputOption::VALUE_REQUIRED, 'Session topic', 'demo');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $sessionId = (string) $input->getOption('session');

        $event = new VoiceDomainEvent(
            $sessionId,
            VoiceDomainEventType::Heartbeat,
            new \DateTimeImmutable(),
            ['message' => 'Mercure connectivity test'],
        );

        $data = [
            'sessionId' => $event->sessionId,
            'type' => $event->type->value,
            'occurredAt' => $event->occurredAt->format(\DateTimeInterface::ATOM),
            'payload' => $event->payload,
        ];

        try {
            $this->hub->publish(new Update(
                topics: ['/voice/dashboard', '/voice/sessions/' . $sessionId],
                data: $this->serializer->serialize($data, 'json'),
                private: false,
            ));
        } catch (\Throwable $e) {
            $io->error($e->getMessage());
            $io->note('Check MERCURE_URL=http://localhost:3000/.well-known/mercure and docker compose mercure (HTTP, not HTTPS redirect).');

            return Command::FAILURE;
        }

        $io->success(sprintf(
            'Published heartbeat to topics /voice/dashboard and /voice/sessions/%s',
            $sessionId,
        ));
        $io->text('Open /sessions/' . $sessionId . ' and check Live transcription or browser EventSource.');

        return Command::SUCCESS;
    }
}
