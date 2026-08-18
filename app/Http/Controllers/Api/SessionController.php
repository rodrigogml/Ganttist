<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class SessionController
{
    public function show(Request $request): JsonResponse
    {
        Log::debug('auth.session.resolved', ['user_id' => $request->user()->id, 'session_id_prefix' => substr($request->session()->getId(), 0, 8)]);

        return response()->json(['user' => $request->user()]);
    }

    public function index(Request $request): JsonResponse
    {
        $currentId = $request->session()->getId();
        $sessions = DB::table('sessions')->where('user_id', $request->user()->id)->orderByDesc('last_activity')->get()->map(fn (object $session): array => ['id' => $session->id, 'current' => hash_equals($currentId, $session->id), 'user_agent' => $session->user_agent, 'last_activity' => $session->last_activity])->all();

        return response()->json(['data' => $sessions]);
    }

    public function destroy(Request $request, string $sessionId): JsonResponse
    {
        $deleted = DB::table('sessions')->where('id', $sessionId)->where('user_id', $request->user()->id)->delete();
        abort_unless($deleted, 404, 'Sessão não encontrada.');
        if (hash_equals($request->session()->getId(), $sessionId)) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json(['message' => 'Sessão revogada.']);
    }
}
