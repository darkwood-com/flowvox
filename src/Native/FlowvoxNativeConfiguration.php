<?php

declare(strict_types=1);

namespace App\Native;

use Symfony\UX\Native\Attribute\AsNativeConfiguration;
use Symfony\UX\Native\Attribute\AsNativeConfigurationProvider;
use Symfony\UX\Native\Configuration\Configuration;
use Symfony\UX\Native\Configuration\Rule;

#[AsNativeConfigurationProvider]
final class FlowvoxNativeConfiguration
{
    #[AsNativeConfiguration('/config/ios_v1.json')]
    public function iosV1(): Configuration
    {
        return new Configuration(
            settings: [
                'enable_pull_to_refresh' => true,
            ],
            rules: [
                new Rule(
                    patterns: ['.*'],
                    properties: [
                        'context' => 'default',
                        'pull_to_refresh_enabled' => true,
                    ],
                ),
                new Rule(
                    patterns: ['/session/.*'],
                    properties: [
                        'pull_to_refresh_enabled' => false,
                    ],
                ),
            ],
        );
    }

    #[AsNativeConfiguration('/config/android_v1.json')]
    public function androidV1(): Configuration
    {
        return new Configuration(
            rules: [
                new Rule(
                    patterns: ['.*'],
                    properties: [
                        'context' => 'default',
                        'pull_to_refresh_enabled' => true,
                    ],
                ),
            ],
        );
    }
}
