<?php

declare(strict_types=1);

namespace App\Application\Transcription;

use App\Domain\Enum\TranscriptionProviderType;

final class TranscriptionProviderRegistry
{
    private readonly TranscriptionProviderType $defaultProvider;

    /** @param iterable<TranscriptionProviderInterface> $providers */
    public function __construct(
        private readonly iterable $providers,
        string $defaultProvider,
    ) {
        $this->defaultProvider = TranscriptionProviderType::from($defaultProvider);
    }

    public function get(TranscriptionProviderType $type): TranscriptionProviderInterface
    {
        foreach ($this->providers as $provider) {
            if ($provider->supports($type)) {
                return $provider;
            }
        }

        throw new \InvalidArgumentException(sprintf('No transcription provider for type "%s"', $type->value));
    }

    public function getDefault(): TranscriptionProviderInterface
    {
        return $this->get($this->defaultProvider);
    }

    public function getDefaultType(): TranscriptionProviderType
    {
        return $this->defaultProvider;
    }
}
