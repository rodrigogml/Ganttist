<?php

namespace App\Http\Controllers;

use App\Mail\MagicLoginLink;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

final class AuthController extends Controller
{
    public function requestLink(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email:rfc', 'max:254']]);
        $email = Str::lower($data['email']);
        $token = Str::random(64);
        $challengeId = (string) Str::ulid();
        DB::table('login_challenges')->insert(['id' => $challengeId, 'email' => $email, 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addMinutes(15), 'created_at' => now(), 'updated_at' => now()]);

        $url = rtrim((string) config('app.url'), '/') . '/?token=' . urlencode($token);

        try {
            Mail::to($email)->send(new MagicLoginLink($url));
        } catch (Throwable $exception) {
            DB::table('login_challenges')->where('id', $challengeId)->delete();
            Log::error('auth.magic_link.delivery_failed', [
                'email_hash' => hash('sha256', $email),
                'exception' => $exception::class,
            ]);

            return response()->json(['message' => 'Não foi possível enviar o link de acesso. Tente novamente.'], 503);
        }

        Log::info('auth.magic_link.sent', ['email_hash' => hash('sha256', $email)]);

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
