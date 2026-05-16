<?php

declare(strict_types=1);

namespace App\Controller;

use App\Application\UseCase\ListActiveSessions;
use App\Repository\TranscriptionRepository;
use App\Service\VoiceWorkerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DashboardController extends AbstractController
{
    #[Route('/', name: 'dashboard')]
    public function index(
        ListActiveSessions $listActiveSessions,
        TranscriptionRepository $transcriptionRepository,
        VoiceWorkerRegistry $registry,
    ): Response {
        return $this->render('dashboard/index.html.twig', [
            'sessions' => $listActiveSessions->execute(),
            'recentTranscriptions' => $transcriptionRepository->createQueryBuilder('t')
                ->orderBy('t.createdAt', 'DESC')
                ->setMaxResults(10)
                ->getQuery()
                ->getResult(),
        ]);
    }
}
