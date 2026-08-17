<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final class TodoistOAuthController extends Controller
{
    public function redirect(Request $request)
    {
        abort_unless(config('services.todoist.client_id'), 503, 'OAuth Todoist ainda não configurado neste ambiente.');
        $state = Str::random(64);
        DB::table('todoist_oauth_states')->insert(['id' => (string) Str::ulid(), 'user_id' => $request->user()->id, 'remember' => (bool) $request->session()->get('login_remember', false), 'state_hash' => hash('sha256', $state), 'expires_at' => now()->addMinutes(10), 'created_at' => now(), 'updated_at' => now()]);

        return redirect()->away('https://app.todoist.com/oauth/authorize?'.http_build_query(['client_id' => config('services.todoist.client_id'), 'scope' => 'data:read_write,data:delete', 'state' => $state]));
    }

    public function callback(Request $request)
    {
        $request->validate(['code' => ['required', 'string'], 'state' => ['required', 'string']]);
        $state = DB::table('todoist_oauth_states')->where('state_hash', hash('sha256', $request->string('state')->toString()))->where('expires_at', '>', now())->first();
        abort_unless($state, 419, 'Estado OAuth inválido ou expirado.');
        Log::info('todoist.oauth.callback.received', ['state_id' => $state->id, 'user_id' => $state->user_id]);
        $response = Http::asForm()->timeout(15)->post('https://todoist.com/oauth/access_token', ['client_id' => config('services.todoist.client_id'), 'client_secret' => config('services.todoist.client_secret'), 'code' => $request->string('code')->toString()])->throw()->json();
        abort_unless(! empty($response['access_token']), 502, 'O Todoist não retornou um token de acesso.');
        DB::table('todoist_oauth_states')->where('id', $state->id)->delete();
        Auth::loginUsingId($state->user_id, (bool) $state->remember);
        $request->session()->regenerate();
        DB::table('todoist_integrations')->updateOrInsert(
            ['user_id' => $state->user_id],
            [
                'id' => (string) Str::ulid(),
                'todoist_user_id' => $response['user_id'] ?? null,
                'access_token_encrypted' => encrypt($response['access_token']),
                'status' => 'active',
                'authorized_at' => now(),
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );
        Log::info('todoist.oauth.connected', ['user_id' => $state->user_id, 'todoist_user_id' => $response['user_id'] ?? null]);

        return redirect('/?todoist=connected');
    }
}
