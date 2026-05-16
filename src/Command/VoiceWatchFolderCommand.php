<?php

declare(strict_types=1);

namespace App\Command;

use App\Application\Transcription\TranscriptionContext;
use App\Application\Transcription\TranscriptionProviderRegistry;
use App\Service\WatchFolderService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'voice:watch-folder',
    description: 'Scan a watch folder and transcribe new audio files (phase 2)',
)]
final class VoiceWatchFolderCommand extends Command
{
    public function __construct(
        #[Autowire('%kernel.project_dir%/var/watch')]
        private readonly string $watchDir,
        private readonly TranscriptionProviderRegistry $providerRegistry,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dir', 'd', InputOption::VALUE_REQUIRED, 'Directory to watch');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dir = $input->getOption('dir') ?? $this->watchDir;
        $service = new WatchFolderService($dir);
        $files = $service->scanNewFiles();

        if ($files === []) {
            $io->note(sprintf('No audio files in %s', $dir));
            return Command::SUCCESS;
        }

        $provider = $this->providerRegistry->getDefault();
        $type = $this->providerRegistry->getDefaultType();

        foreach ($files as $path) {
            $io->writeln(sprintf('Transcribing %s…', $path));
            $result = $provider->transcribeFile($path, new TranscriptionContext('watch-folder', $type));
            $io->writeln($result->text);
        }

        return Command::SUCCESS;
    }
}
