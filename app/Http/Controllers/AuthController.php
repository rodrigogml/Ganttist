<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final class AuthController extends Controller
{
    public function requestLink(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email:rfc', 'max:254']]);
        $token = Str::random(64);
        DB::table('login_challenges')->insert(['id' => (string) Str::ulid(), 'email' => Str::lower($data['email']), 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addMinutes(15), 'created_at' => now(), 'updated_at' => now()]);
        Log::info('auth.magic_link.requested', ['email_hash' => hash('sha256', Str::lower($data['email'])), 'development_token' => app()->isLocal() ? $token : null]);

        return response()->json(['message' => 'Se o e-mail puder ser utilizado, enviaremos um link de acesso.'], 202);
    }

    public function verify(Request $request): JsonResponse
    {
        $data = $request->validate(['token' => ['required', 'string', 'size:64']]);
        $challenge = DB::table('login_challenges')->where('token_hash', hash('sha256', $data['token']))->whereNull('consumed_at')->where('expires_at', '>', now())->lockForUpdate()->first();
        if (! $challenge) {
            return response()->json(['message' => 'Link inválido ou expirado.'], 422);
        }
        DB::transaction(function () use ($challenge) {
            DB::table('login_challenges')->where('id', $challenge->id)->update(['consumed_at' => now(), 'updated_at' => now()]);
            $user = User::firstOrCreate(['email' => $challenge->email], ['timezone' => 'America/Sao_Paulo', 'status' => 'active', 'email_verified_at' => now()]);
            Auth::login($user);
        });
        $request->session()->regenerate();

        return response()->json(['message' => 'Acesso confirmado.', 'user' => Auth::user()]);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Sessão encerrada.']);
    }
}
