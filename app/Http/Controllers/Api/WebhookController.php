<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessTodoistEvent;
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
        abort_if(strlen($request->getContent()) > 262144, 413, 'Payload de webhook excede o limite permitido.');
        try {
            $payload = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            abort(422, 'Payload de webhook invÃ¡lido.');
        }
        abort_unless(is_array($payload), 422, 'Payload de webhook invÃ¡lido.');
        $eventId = $sync->markEvent($payload, true);
        if ($eventId !== null) {
            ProcessTodoistEvent::dispatch($eventId)->onQueue('sync');
        }

        return response()->json(['accepted' => true], 202);
    }
}
