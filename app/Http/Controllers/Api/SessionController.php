<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SessionController
{
    public function show(Request $request): JsonResponse
    {
        return response()->json(['user' => $request->user()]);
    }
}
