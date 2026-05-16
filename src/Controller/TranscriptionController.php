<?php

declare(strict_types=1);

namespace App\Controller;

use App\Application\UseCase\ExportTranscription;
use App\Application\UseCase\SearchTranscriptions;
use App\Entity\Transcription;
use App\Repository\TranscriptionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Ulid;

final class TranscriptionController extends AbstractController
{
    #[Route('/transcriptions', name: 'transcription_index', methods: ['GET'])]
    public function index(Request $request, SearchTranscriptions $search): Response
    {
        $query = (string) $request->query->get('q', '');

        return $this->render('transcription/index.html.twig', [
            'query' => $query,
            'results' => $search->execute($query),
        ]);
    }

    #[Route('/transcriptions/{id}', name: 'transcription_show', methods: ['GET'])]
    public function show(string $id, TranscriptionRepository $repository): Response
    {
        $transcription = $repository->find(Ulid::fromString($id));
        if (!$transcription instanceof Transcription) {
            throw $this->createNotFoundException();
        }

        return $this->render('transcription/show.html.twig', [
            'transcription' => $transcription,
        ]);
    }

    #[Route('/transcriptions/{id}/export.{format}', name: 'transcription_export', methods: ['GET'], requirements: ['format' => 'txt|md|srt|vtt'])]
    public function export(
        string $id,
        string $format,
        ExportTranscription $export,
        Request $request,
    ): Response {
        $removeFillers = $request->query->getBoolean('remove_fillers');
        $content = $export->execute($id, $format, $removeFillers);
        if ($content === null) {
            throw $this->createNotFoundException();
        }

        $mime = match ($format) {
            'md' => 'text/markdown',
            'srt' => 'application/x-subrip',
            'vtt' => 'text/vtt',
            default => 'text/plain',
        };

        return new Response($content, 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => sprintf('attachment; filename="transcription.%s"', $format),
        ]);
    }
}
