<?php

declare(strict_types=1);

namespace App\Application\UseCase;

use App\Entity\Transcription;
use App\Repository\TranscriptionRepository;

final readonly class SearchTranscriptions
{
    public function __construct(
        private TranscriptionRepository $repository,
    ) {
    }

    /**
     * @return list<Transcription>
     */
    public function execute(string $query, int $limit = 50): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        return $this->repository->searchByText($query, $limit);
    }
}
