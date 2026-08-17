<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

final class SessionController
{
    public function show(Request $request): JsonResponse
    {
        Log::debug('auth.session.resolved', ['user_id' => $request->user()->id, 'session_id_prefix' => substr($request->session()->getId(), 0, 8)]);

        return response()->json(['user' => $request->user()]);
    }
}
