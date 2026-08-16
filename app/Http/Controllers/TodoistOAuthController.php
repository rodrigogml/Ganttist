<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

final class TodoistOAuthController extends Controller
{
    public function redirect(Request $request)
    {
        abort_unless(config('services.todoist.client_id'), 503, 'OAuth Todoist ainda não configurado neste ambiente.');
        $state = Str::random(40);
        $request->session()->put('todoist_oauth_state', $state);

        return redirect()->away('https://todoist.com/oauth/authorize?'.http_build_query(['client_id' => config('services.todoist.client_id'), 'scope' => 'data:read_write,data:delete', 'state' => $state]));
    }

    public function callback(Request $request)
    {
        $request->validate(['code' => ['required', 'string'], 'state' => ['required', 'string']]);
        abort_unless(hash_equals((string) $request->session()->pull('todoist_oauth_state'), $request->string('state')->toString()), 419, 'Estado OAuth inválido.');
        $response = Http::asForm()->timeout(15)->post('https://todoist.com/oauth/access_token', ['client_id' => config('services.todoist.client_id'), 'client_secret' => config('services.todoist.client_secret'), 'code' => $request->string('code')->toString()])->throw()->json();
        // Persistência final depende de usuário autenticado e credenciais reais; nunca expõe o token ao navegador.
        $request->session()->put('todoist_access_token_pending', encrypt($response['access_token']));

        return redirect('/?todoist=connected');
    }
}
