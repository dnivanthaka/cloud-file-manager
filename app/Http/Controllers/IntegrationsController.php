<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ProviderType;
use App\Jobs\SyncIntegrationFilesJob;
use App\Services\TokenRefreshService;
use Exception;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Socialite\Facades\Socialite;

class IntegrationsController extends Controller
{
    private const ONE_YEAR_IN_SECONDS = 60 * 60 * 24 * 365;
    // Inject your refactored Token Refresh Service directly into the constructor
    public function __construct(protected TokenRefreshService $tokenService) {}

    /**
     * Display the main integrations management screen.
     */
    public function index(): Response
    {
        $integrations = auth()->user()->integrations()
            ->get(['id', 'provider', 'account_email', 'account_name', 'created_at'])
            ->groupBy(fn ($item) => $item->provider->value);

        return Inertia::render('Integrations/Index', [
            'linkedIntegrations' => $integrations,
        ]);
    }

    /**
     * Dynamic Redirect: Hand off the user browser to the chosen provider's OAuth server.
     */
    public function redirectToProvider(string $providerStr)
    {
        // 1. Resolve string to type-safe Backed Enum instance
        $provider = ProviderType::from($providerStr);

        // 2. Initialize Socialite driver with provider configurations out of the Enum
        $driver = Socialite::driver($provider->value)->scopes($provider->scopes());

        // 3. Apply offline parameters if the integration demands lifetime refresh tokens
        if ($provider->requiresOfflineAccess()) {
            $driver->with([
                'access_type' => $provider->requiresOfflineAccess() ? 'offline' : 'online',
                'prompt' => 'consent', // Guarantees a refresh_token is sent on every re-auth
            ]);
        }

        return $driver->redirect();
    }

    /**
     * Dynamic Callback: Capture incoming security tokens and create database links.
     */
    public function handleProviderCallback(string $providerStr)
    {
        try {
            $provider = ProviderType::from($providerStr);
            $socialiteUser = Socialite::driver($provider->value)->user();

            auth()->user()->integrations()->updateOrCreate(
                [
                    'provider' => $provider,
                    'account_email' => $socialiteUser->getEmail() ?? $socialiteUser->getId(),
                ],
                [
                    'account_name' => $socialiteUser->getName() ?? $socialiteUser->getNickname(),
                    'access_token' => $socialiteUser->token,
                    'expires_at' => time() + ($socialiteUser->expiresIn ?? self::ONE_YEAR_IN_SECONDS),
                    'refresh_token' => $socialiteUser->refreshToken ?? DB::raw('refresh_token'),
                ]
            );

            return redirect()->route('integrations.index');
        } catch (Exception) {
            return redirect()->route('integrations.index')->with('error', "Authentication with {$providerStr} failed.");
        }
    }

    /**
     * Remove an active integration connection profile cleanly.
     */
    public function destroy(int $integrationId)
    {
        // Find the specific integration belonging strictly to the logged-in user
        $integration = auth()->user()->integrations()->findOrFail($integrationId);

        // Permanently wipe the row from the database
        $integration->delete();

        // Redirect right back to the index view refreshing the Inertia state data layout
        return redirect()->route('integrations.index')
            ->with('success', 'Integration successfully disconnected.');
    }

    /**
     * Dispatch an asynchronous background sync job layer thread.
     */
    public function sync(int $integrationId)
    {
        $integration = auth()->user()->integrations()->findOrFail($integrationId);

        // Send task directly to the background Redis queue service worker
        SyncIntegrationFilesJob::dispatch($integration);

        return redirect()->route('integrations.index')
            ->with('success', 'File synchronization worker dispatched successfully in background.');
    }
}
