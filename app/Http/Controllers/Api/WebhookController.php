<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TodoistSyncService;
use Illuminate\Http\Request;

final class WebhookController extends Controller
{
    public function __invoke(Request $request, TodoistSyncService $sync)
    {
        $secret = (string) config('services.todoist.webhook_secret');
        $signature = (string) $request->header('X-Todoist-Hmac-SHA256');
        if ($secret === '' || ! hash_equals(base64_encode(hash_hmac('sha256', $request->getContent(), $secret, true)), $signature)) {
            abort(401, 'Assinatura de webhook inválida.');
        }
        $payload = $request->json()->all();
        $sync->markEvent($payload);

        return response()->json(['accepted' => true], 202);
    }
}
