<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Phase 2: watches a directory and enqueues audio files for transcription.
 */
final class WatchFolderService
{
    public function __construct(
        private readonly string $watchDir,
    ) {
    }

    /**
     * @return list<string> absolute paths of new audio files
     */
    public function scanNewFiles(): array
    {
        if (!is_dir($this->watchDir)) {
            return [];
        }

        $files = [];
        $iterator = new \FilesystemIterator($this->watchDir, \FilesystemIterator::SKIP_DOTS);
        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }
            $ext = strtolower($file->getExtension());
            if (!\in_array($ext, ['wav', 'mp3', 'm4a', 'ogg', 'opus'], true)) {
                continue;
            }
            $files[] = $file->getPathname();
        }

        return $files;
    }
}
