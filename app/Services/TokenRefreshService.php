<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Integration;
use Exception;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;

class TokenRefreshService
{
    public function getValidToken(Integration $integration): string
    {
        // 1. If the access token is still perfectly fine, bypass network calls entirely
        if (! $integration->isExpired()) {
            return $integration->access_token;
        }

        if (! $integration->refresh_token) {
            throw new Exception("Missing long-lived refresh token for integration ID: {$integration->id}");
        }

        $providerStr = $integration->provider->value;

        try {
            // 2. ⚡ Call Socialite's native token refresh driver method directly
            $response = Socialite::driver($providerStr)->refreshToken($integration->refresh_token);

            // 3. Extract the fresh properties out of Socialite's token response object
            $integration->update([
                'access_token' => $response->token,
                'access_token_expires_at' => time() + ($response->expiresIn ?? 3600),
                // Google occasionally returns a brand new refresh token alongside the access token
                'refresh_token' => $response->refreshToken ?? $integration->refresh_token,
            ]);

            return $integration->access_token;

        } catch (Exception $e) {
            // 4. Reactive error handling: if the handshake throws, the refresh token is dead
            Log::error("Socialite token refresh failed for {$providerStr}: ".$e->getMessage());

            // Clear the dead integration out of the database so they are prompted to link it fresh
            $integration->delete();

            throw new Exception("The connection to {$providerStr} has been revoked or expired. Please link your account again.");
        }
    }
}
