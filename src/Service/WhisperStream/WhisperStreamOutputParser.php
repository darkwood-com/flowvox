<?php

declare(strict_types=1);

namespace App\Service\WhisperStream;

/**
 * Parses whisper-stream VAD mode output (### Transcription N START/END + timestamped lines).
 */
final class WhisperStreamOutputParser
{
    private int $lastCompletedIteration = -1;

    private ?int $currentIteration = null;

    private string $accumulatedText = '';

    /** @var array<int, string> */
    private array $blocks = [];

    /** @var array<int, string> */
    private array $lastPreviewByIteration = [];

    private ?string $lastSlidingPreview = null;

    /**
     * @return list<string> New segment texts detected in this chunk
     */
    public function feed(string $chunk): array
    {
        if ($chunk === '') {
            return [];
        }

        $chunk = preg_replace('/\x1b\[[0-9;?]*[ -\/]*[@-~]/', '', $chunk) ?? $chunk;
        if (!str_contains($chunk, "\n") && str_contains($chunk, "\r")) {
            $parts = explode("\r", $chunk);
            $chunk = (string) end($parts);
        }

        $newPartials = [];
        $lines = preg_split('/\r\n|\r|\n/', $chunk) ?: [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '[Start speaking]')) {
                continue;
            }

            if (preg_match('/^### Transcription (\d+) START\b/', $line, $m)) {
                $this->currentIteration = (int) $m[1];
                if (!isset($this->blocks[$this->currentIteration])) {
                    $this->blocks[$this->currentIteration] = '';
                }
                continue;
            }

            if (preg_match('/^### Transcription (\d+) END\b/', $line, $m)) {
                $iteration = (int) $m[1];
                if (isset($this->blocks[$iteration])) {
                    $blockText = trim($this->blocks[$iteration]);
                    if ($blockText !== '' && $iteration > $this->lastCompletedIteration) {
                        $this->lastCompletedIteration = $iteration;
                        $this->accumulatedText = trim($this->accumulatedText . ' ' . $blockText);
                        if ($blockText !== ($this->lastPreviewByIteration[$iteration] ?? '')) {
                            $newPartials[] = $blockText;
                        }
                    }
                    unset($this->blocks[$iteration], $this->lastPreviewByIteration[$iteration]);
                }
                if ($this->currentIteration === $iteration) {
                    $this->currentIteration = null;
                }
                continue;
            }

            if (preg_match('/^\[[\d:.,\s]+\s+-->\s+[\d:.,\s]+\]\s*(.*)$/u', $line, $m)) {
                $text = trim($m[1]);
                if ($text === '' || $text === '[BLANK_AUDIO]') {
                    continue;
                }
                $iteration = $this->currentIteration ?? ($this->lastCompletedIteration + 1);
                if (!isset($this->blocks[$iteration])) {
                    $this->blocks[$iteration] = '';
                }
                $this->blocks[$iteration] = trim($this->blocks[$iteration] . ' ' . $text);

                $preview = $this->blocks[$iteration];
                if ($preview !== ($this->lastPreviewByIteration[$iteration] ?? '')) {
                    $this->lastPreviewByIteration[$iteration] = $preview;
                    $newPartials[] = $preview;
                }
                continue;
            }

            if (strlen($line) > 1 && $line !== ($this->lastSlidingPreview ?? '')) {
                $this->lastSlidingPreview = $line;
                $newPartials[] = $line;
            }
        }

        return $newPartials;
    }

    public function getAccumulatedText(): string
    {
        $pending = '';
        foreach ($this->blocks as $text) {
            $pending = trim($pending . ' ' . trim($text));
        }

        return trim($this->accumulatedText . ' ' . $pending);
    }

    public function reset(): void
    {
        $this->lastCompletedIteration = -1;
        $this->currentIteration = null;
        $this->accumulatedText = '';
        $this->blocks = [];
        $this->lastPreviewByIteration = [];
        $this->lastSlidingPreview = null;
    }
}
