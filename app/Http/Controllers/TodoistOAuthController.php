<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

final class TodoistOAuthController extends Controller
{
    public function redirect(Request $request)
    {
        $integration = DB::table('todoist_integrations')->where('user_id', $request->user()->id)->where('status', 'active')->whereNotNull('access_token_encrypted')->first();
        if ($integration) {
            Log::info('todoist.oauth.authorization_skipped', ['user_id' => $request->user()->id, 'reason' => 'existing_active_integration']);

            return redirect('/?todoist=connected');
        }
        abort_unless(config('services.todoist.client_id'), 503, 'OAuth Todoist ainda não configurado neste ambiente.');
        $state = Str::random(64);
        $project = DB::table('gantt_projects')->where('user_id', $request->user()->id)->where('status', 'active')->first();
        Log::info('todoist.oauth.authorization_started', ['user_id' => $request->user()->id, 'has_active_integration' => $integration !== null, 'has_active_project' => $project !== null]);
        DB::table('todoist_oauth_states')->insert(['id' => (string) Str::ulid(), 'user_id' => $request->user()->id, 'remember' => (bool) $request->session()->get('login_remember', false), 'state_hash' => hash('sha256', $state), 'expires_at' => now()->addMinutes(10), 'created_at' => now(), 'updated_at' => now()]);

        return redirect()->away('https://app.todoist.com/oauth/authorize?'.http_build_query(['client_id' => config('services.todoist.client_id'), 'scope' => 'data:read_write,data:delete', 'state' => $state]));
    }

    public function callback(Request $request)
    {
        if (! $request->filled(['code', 'state'])) {
            Log::warning('todoist.oauth.callback.rejected', ['reason' => $request->filled('error') ? 'provider_rejected' : 'missing_parameters']);

            return redirect('/?todoist=authorization_cancelled');
        }

        $state = DB::table('todoist_oauth_states')->where('state_hash', hash('sha256', $request->string('state')->toString()))->where('expires_at', '>', now())->whereNull('consumed_at')->first();
        if (! $state) {
            Log::warning('todoist.oauth.callback.rejected', ['reason' => 'invalid_or_expired_state']);

            return redirect('/?todoist=authorization_expired');
        }

        Log::info('todoist.oauth.callback.received', ['state_id' => $state->id, 'user_id' => $state->user_id]);

        try {
            $response = Http::asForm()->timeout(15)->post(config('services.todoist.oauth_token_url'), ['client_id' => config('services.todoist.client_id'), 'client_secret' => config('services.todoist.client_secret'), 'code' => $request->string('code')->toString()])->throw()->json();
            if (empty($response['access_token'])) {
                throw new \RuntimeException('Todoist access token missing.');
            }

            DB::transaction(function () use ($state, $response): void {
                $currentState = DB::table('todoist_oauth_states')->where('id', $state->id)->where('expires_at', '>', now())->whereNull('consumed_at')->lockForUpdate()->first();
                if (! $currentState) {
                    throw new \RuntimeException('Todoist OAuth state is no longer available.');
                }

                $existingIntegration = DB::table('todoist_integrations')->where('user_id', $state->user_id)->lockForUpdate()->first();
                $values = ['todoist_user_id' => $response['user_id'] ?? null, 'access_token_encrypted' => encrypt($response['access_token']), 'status' => 'active', 'sync_state' => 'synced', 'last_sync_error' => null, 'authorized_at' => now(), 'token_rotated_at' => now(), 'updated_at' => now()];
                if (isset($response['expires_in'])) {
                    $values['access_token_expires_at'] = now()->addSeconds((int) $response['expires_in']);
                }
                if (! empty($response['refresh_token'])) {
                    $values['refresh_token_encrypted'] = encrypt($response['refresh_token']);
                } elseif ($existingIntegration?->refresh_token_encrypted) {
                    $values['refresh_token_encrypted'] = $existingIntegration->refresh_token_encrypted;
                }
                if ($existingIntegration) {
                    DB::table('todoist_integrations')->where('id', $existingIntegration->id)->update($values);
                } else {
                    DB::table('todoist_integrations')->insert(['id' => (string) Str::ulid(), 'user_id' => $state->user_id, ...$values, 'created_at' => now()]);
                }
                DB::table('todoist_oauth_states')->where('id', $state->id)->update(['consumed_at' => now(), 'updated_at' => now()]);
            });
        } catch (Throwable $exception) {
            Log::error('todoist.oauth.callback.failed', ['state_id' => $state->id, 'user_id' => $state->user_id, 'exception' => $exception::class]);

            return redirect('/?todoist=authorization_failed');
        }

        Auth::loginUsingId($state->user_id, (bool) $state->remember);
        $request->session()->regenerate();
        Log::info('todoist.oauth.connected', ['user_id' => $state->user_id, 'todoist_user_id' => $response['user_id'] ?? null]);

        return redirect('/?todoist=connected');
    }
}
