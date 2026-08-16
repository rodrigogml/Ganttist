<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class WebhookController extends Controller
{
    public function __invoke(Request $request)
    {
        $secret = (string) config('services.todoist.webhook_secret');
        $signature = (string) $request->header('X-Todoist-Hmac-SHA256');
        if ($secret === '' || ! hash_equals(base64_encode(hash_hmac('sha256', $request->getContent(), $secret, true)), $signature)) {
            abort(401, 'Assinatura de webhook inválida.');
        }
        $payload = $request->json()->all();
        $externalId = (string) ($payload['event_id'] ?? hash('sha256', $request->getContent()));
        DB::table('todoist_events')->insertOrIgnore(['id' => (string) Str::ulid(), 'external_event_id' => $externalId, 'event_type' => (string) ($payload['event_name'] ?? 'unknown'), 'payload' => json_encode($payload, JSON_THROW_ON_ERROR), 'created_at' => now(), 'updated_at' => now()]);

        return response()->json(['accepted' => true], 202);
    }
}
