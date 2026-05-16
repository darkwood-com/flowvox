<?php

declare(strict_types=1);

namespace App\Application\UseCase;

use App\Application\UseCase\ApplyFillerRemoval;
use App\Entity\Transcription;
use App\Repository\TranscriptionRepository;

final readonly class ExportTranscription
{
    public function __construct(
        private TranscriptionRepository $repository,
        private ApplyFillerRemoval $fillerRemoval,
    ) {
    }

    public function execute(string $transcriptionId, string $format, bool $removeFillers = false): ?string
    {
        $transcription = $this->repository->find($transcriptionId);
        if (!$transcription instanceof Transcription) {
            return null;
        }

        $text = $transcription->getFullText();
        if ($removeFillers) {
            $text = $this->fillerRemoval->execute($text);
        }

        return match ($format) {
            'txt', 'md' => $this->exportText($text, $format),
            'srt' => $this->exportSrt($transcription),
            'vtt' => $this->exportVtt($transcription),
            default => null,
        };
    }

    private function exportText(string $text, string $format): string
    {
        if ($format === 'md') {
            return "# Transcription\n\n" . $text . "\n";
        }

        return $text . "\n";
    }

    private function exportSrt(Transcription $transcription): string
    {
        $lines = [];
        $index = 1;
        foreach ($transcription->getSegments() as $segment) {
            if (!$segment->isFinal()) {
                continue;
            }
            $start = $this->formatSrtTime(0);
            $end = $this->formatSrtTime(3);
            $lines[] = (string) $index++;
            $lines[] = $start . ' --> ' . $end;
            $lines[] = $segment->getText();
            $lines[] = '';
        }

        if ($lines === [] && $transcription->getFullText() !== '') {
            $lines[] = '1';
            $lines[] = '00:00:00,000 --> 00:00:03,000';
            $lines[] = $transcription->getFullText();
            $lines[] = '';
        }

        return implode("\n", $lines);
    }

    private function exportVtt(Transcription $transcription): string
    {
        $body = "WEBVTT\n\n";
        foreach ($transcription->getSegments() as $segment) {
            if (!$segment->isFinal()) {
                continue;
            }
            $body .= "00:00:00.000 --> 00:00:03.000\n";
            $body .= $segment->getText() . "\n\n";
        }

        if ($transcription->getSegments()->isEmpty() && $transcription->getFullText() !== '') {
            $body .= "00:00:00.000 --> 00:00:03.000\n";
            $body .= $transcription->getFullText() . "\n\n";
        }

        return $body;
    }

    private function formatSrtTime(int $seconds): string
    {
        $h = intdiv($seconds, 3600);
        $m = intdiv($seconds % 3600, 60);
        $s = $seconds % 60;

        return sprintf('%02d:%02d:%02d,000', $h, $m, $s);
    }
}
