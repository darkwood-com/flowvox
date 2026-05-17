<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\Enum\TranscriptionProviderType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class SettingsController extends AbstractController
{
    #[Route('/settings', name: 'settings', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        $providers = array_map(static fn (TranscriptionProviderType $t): string => $t->value, TranscriptionProviderType::cases());
        $current = $_ENV['FLOWVOX_TRANSCRIPTION_PROVIDER'] ?? 'whisper_cpp';

        if ($request->isMethod('POST')) {
            $this->addFlash('info', 'Set FLOWVOX_TRANSCRIPTION_PROVIDER in .env.local and restart workers to apply.');
        }

        return $this->render('settings/index.html.twig', [
            'providers' => $providers,
            'current' => $current,
            'openaiConfigured' => ($_ENV['OPENAI_API_KEY'] ?? '') !== '',
            'whisperMode' => $_ENV['FLOWVOX_WHISPER_MODE'] ?? 'batch',
            'whisperStreamPath' => $_ENV['WHISPER_STREAM_PATH'] ?? '',
        ]);
    }
}
