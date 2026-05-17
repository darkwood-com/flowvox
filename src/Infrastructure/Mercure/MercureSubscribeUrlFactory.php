<?php

declare(strict_types=1);

namespace App\Infrastructure\Mercure;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mercure\HubRegistry;

/**
 * Builds Mercure subscribe URLs with JWT in the query string (no cookie).
 * Required when the app is opened via LAN IP while MERCURE_PUBLIC_URL uses localhost.
 */
final readonly class MercureSubscribeUrlFactory
{
    public function __construct(
        private HubRegistry $hubRegistry,
        private string $mercurePublicUrl,
    ) {
    }

    /**
     * @param string[] $topics
     */
    public function create(Request $request, array $topics, ?string $hub = null): string
    {
        $hubInstance = $this->hubRegistry->getHub($hub);
        $factory = $hubInstance->getFactory();
        if (null === $factory) {
            throw new \RuntimeException('Mercure hub has no JWT factory.');
        }

        $url = $this->resolvePublicUrl($request);
        $separator = str_contains($url, '?') ? '&' : '?';
        foreach ($topics as $topic) {
            $url .= $separator.'topic='.rawurlencode($topic);
            $separator = '&';
        }

        $token = $factory->create($topics, [], []);

        return $url.'&authorization='.rawurlencode($token);
    }

    private function resolvePublicUrl(Request $request): string
    {
        $parts = parse_url($this->mercurePublicUrl);
        if (!\is_array($parts) || !isset($parts['host'])) {
            return $this->mercurePublicUrl;
        }

        $configuredHost = strtolower($parts['host']);
        $requestHost = strtolower($request->getHost());

        if ($configuredHost === $requestHost) {
            return $this->mercurePublicUrl;
        }

        // Dev: Symfony on LAN IP, Mercure env still localhost → use same host as the page.
        if (\in_array($configuredHost, ['localhost', '127.0.0.1'], true)) {
            $scheme = $parts['scheme'] ?? 'http';
            $port = $parts['port'] ?? ($scheme === 'https' ? 443 : 80);
            $path = $parts['path'] ?? '/.well-known/mercure';

            return sprintf('%s://%s:%d%s', $scheme, $requestHost, $port, $path);
        }

        return $this->mercurePublicUrl;
    }
}
