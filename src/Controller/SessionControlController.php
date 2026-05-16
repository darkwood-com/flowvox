<?php

declare(strict_types=1);

namespace App\Controller;

use App\Application\UseCase\SendVoiceControl;
use App\Enum\VoiceControlType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;

final class SessionControlController extends AbstractController
{
    #[Route('/sessions/{sessionId}/start', name: 'session_start', methods: ['POST'])]
    #[IsCsrfTokenValid('session_control')]
    public function start(string $sessionId, SendVoiceControl $sendVoiceControl, Request $request): Response
    {
        $sent = $sendVoiceControl->execute(VoiceControlType::START, $sessionId);

        if ($request->headers->get('Turbo-Frame')) {
            return $this->render('session/_controls.html.twig', [
                'sessionId' => $sessionId,
                'flash' => $sent !== [] ? 'START sent' : 'No active worker for this session',
            ]);
        }

        $this->addFlash('success', $sent !== [] ? 'START sent' : 'No active worker for this session');

        return $this->redirectToRoute('session_show', ['sessionId' => $sessionId]);
    }

    #[Route('/sessions/{sessionId}/stop', name: 'session_stop', methods: ['POST'])]
    #[IsCsrfTokenValid('session_control')]
    public function stop(string $sessionId, SendVoiceControl $sendVoiceControl, Request $request): Response
    {
        $sent = $sendVoiceControl->execute(VoiceControlType::STOP, $sessionId);

        if ($request->headers->get('Turbo-Frame')) {
            return $this->render('session/_controls.html.twig', [
                'sessionId' => $sessionId,
                'flash' => $sent !== [] ? 'STOP sent' : 'No active worker for this session',
            ]);
        }

        $this->addFlash('success', $sent !== [] ? 'STOP sent' : 'No active worker for this session');

        return $this->redirectToRoute('session_show', ['sessionId' => $sessionId]);
    }
}
