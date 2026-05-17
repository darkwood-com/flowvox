<?php

declare(strict_types=1);

namespace App\Controller;

use App\Application\UseCase\GetSessionDetail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class SessionController extends AbstractController
{
    #[Route('/sessions/{sessionId}', name: 'session_show', methods: ['GET'])]
    public function show(string $sessionId, GetSessionDetail $getSessionDetail): Response
    {
        $detail = $getSessionDetail->execute($sessionId);
        if ($detail === null) {
            throw $this->createNotFoundException(sprintf('Session "%s" not found.', $sessionId));
        }

        return $this->render('session/show.html.twig', [
            'session' => $detail['session'],
            'transcriptions' => $detail['transcriptions'],
            'mercure_topic' => '/voice/sessions/' . $sessionId,
            'whisperStreamMode' => ($_ENV['FLOWVOX_WHISPER_MODE'] ?? 'batch') === 'stream',
        ]);
    }
}
