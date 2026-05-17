<?php

declare(strict_types=1);

namespace App\Command;

use App\Domain\Enum\VoiceDomainEventType;
use App\Domain\Event\VoiceDomainEvent;
use App\Infrastructure\Navi\VoiceEventTraceLogger;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'voice:trace-test',
    description: 'Write sample voice events to the Navi NDJSON trace (requires FLOWVOX_NAVI_TRACE=1)',
)]
final class VoiceTraceTestCommand extends Command
{
    public function __construct(
        private readonly VoiceEventTraceLogger $traceLogger,
        private readonly string $traceLogFile,
        private readonly bool $traceEnabled,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('session', 's', InputOption::VALUE_REQUIRED, 'Session ID', 'trace-test');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $sessionId = (string) $input->getOption('session');

        if (!$this->traceEnabled) {
            $io->warning('FLOWVOX_NAVI_TRACE is disabled. Set FLOWVOX_NAVI_TRACE=1 in .env.local and retry.');

            return Command::FAILURE;
        }

        $now = new \DateTimeImmutable();

        $this->traceLogger->trace(new VoiceDomainEvent(
            $sessionId,
            VoiceDomainEventType::RecordingStarted,
            $now,
            ['provider' => 'trace_test', 'source' => 'voice:trace-test'],
        ));

        $this->traceLogger->trace(new VoiceDomainEvent(
            $sessionId,
            VoiceDomainEventType::TranscriptionPartial,
            $now,
            ['text' => 'Bonjour, ceci est un test de trace Navi pour Flowvox.'],
        ));

        $this->traceLogger->trace(new VoiceDomainEvent(
            $sessionId,
            VoiceDomainEventType::TranscriptionFinal,
            $now,
            ['text' => 'Bonjour, ceci est un test de trace Navi pour Flowvox.', 'provider' => 'trace_test'],
        ));

        $io->success(sprintf('Wrote 3 trace lines to %s', $this->traceLogFile));

        return Command::SUCCESS;
    }
}
