<?php

declare(strict_types=1);

namespace App\Enums;

use InvalidArgumentException;

enum ProviderType: string
{
    case GOOGLE = 'google';
    case DROPBOX = 'dropbox';

    /**
     * Fetch configuration array for the enum instance dynamically.
     */
    public function config(): array
    {
        $config = config("integrations.providers.{$this->value}");

        if (! $config) {
            throw new InvalidArgumentException("Configuration metadata for provider [{$this->value}] is missing.");
        }

        return $config;
    }

    public function tokenUrl(): string
    {
        return $this->config()['token_url'];
    }

    public function scopes(): array
    {
        return $this->config()['scopes'];
    }

    public function requiresOfflineAccess(): bool
    {
        return $this->config()['offline'] ?? false;
    }
}
