<?php

namespace App\Services;

use App\Exceptions\TodoistReauthorizationRequired;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

final class TodoistAccessTokenService
{
    public function accessToken(object $integration, bool $forceRefresh = false): string
    {
        return Cache::lock('todoist:token-refresh:'.$integration->id, 30)->block(
            10,
            fn (): string => $this->currentAccessToken((string) $integration->id, $forceRefresh),
        );
    }

    private function currentAccessToken(string $integrationId, bool $forceRefresh): string
    {
        $integration = DB::table('todoist_integrations')->where('id', $integrationId)->first();
        if (! $integration?->access_token_encrypted) {
            throw new TodoistReauthorizationRequired;
        }

        $token = decrypt($integration->access_token_encrypted);
        $expiresAt = $integration->access_token_expires_at ?? null;
        $refreshToken = $integration->refresh_token_encrypted ?? null;

        if ($refreshToken === null || (! $forceRefresh && $expiresAt !== null && now()->addMinute()->lt(Carbon::parse($expiresAt)))) {
            return $token;
        }

        $response = Http::asForm()->timeout(15)->post(config('services.todoist.oauth_token_url'), [
            'client_id' => config('services.todoist.client_id'),
            'client_secret' => config('services.todoist.client_secret'),
            'grant_type' => 'refresh_token',
            'refresh_token' => decrypt($refreshToken),
        ]);
        $payload = $response->json();
        $error = is_array($payload) ? ($payload['error'] ?? null) : null;

        if (! $response->successful()) {
            Log::warning('todoist.oauth.refresh_failed', [
                'integration_id' => $integration->id,
                'status' => $response->status(),
                'error' => is_string($error) ? $error : 'provider_error',
            ]);
            if ($error === 'invalid_grant') {
                $this->markReauthorizationRequired($integration);
                throw new TodoistReauthorizationRequired;
            }

            $response->throw();
        }

        if (empty($payload['access_token'])) {
            throw new RuntimeException('Todoist returned a successful refresh response without an access token.');
        }
        if (empty($payload['refresh_token'])) {
            Log::warning('todoist.oauth.refresh_token_missing', ['integration_id' => $integration->id]);
            $this->markReauthorizationRequired($integration);
            throw new TodoistReauthorizationRequired;
        }

        $values = [
            'access_token_encrypted' => encrypt($payload['access_token']),
            'refresh_token_encrypted' => encrypt($payload['refresh_token']),
            'access_token_expires_at' => isset($payload['expires_in']) ? now()->addSeconds((int) $payload['expires_in']) : null,
            'token_rotated_at' => now(),
            'updated_at' => now(),
        ];
        if ($integration->status === 'reauthorization_required') {
            $values['status'] = 'active';
            $values['sync_state'] = 'synced';
            $values['last_sync_error'] = null;
        }
        DB::table('todoist_integrations')->where('id', $integration->id)->update($values);
        Log::info('todoist.oauth.token_refreshed', ['integration_id' => $integration->id]);

        return (string) $payload['access_token'];
    }

    private function markReauthorizationRequired(object $integration): void
    {
        DB::table('todoist_integrations')->where('id', $integration->id)->update([
            'status' => 'reauthorization_required',
            'sync_state' => 'reauthorization_required',
            'last_sync_error' => 'authorization_revoked',
            'updated_at' => now(),
        ]);
    }
}
