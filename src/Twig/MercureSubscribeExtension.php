<?php

declare(strict_types=1);

namespace App\Twig;

use App\Infrastructure\Mercure\MercureSubscribeUrlFactory;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class MercureSubscribeExtension extends AbstractExtension
{
    public function __construct(
        private readonly MercureSubscribeUrlFactory $subscribeUrlFactory,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('mercure_subscribe_url', $this->mercureSubscribeUrl(...)),
        ];
    }

    /**
     * @param string|string[] $topics
     */
    public function mercureSubscribeUrl(string|array $topics): string
    {
        $request = $this->requestStack->getCurrentRequest();
        if (null === $request) {
            throw new \RuntimeException('mercure_subscribe_url() requires an HTTP request.');
        }

        return $this->subscribeUrlFactory->create($request, (array) $topics);
    }
}
