<?php

use App\Http\Controllers\Api\ScheduleSimulationController;
use App\Http\Controllers\Api\SessionController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\TodoistController;
use App\Http\Controllers\Api\WebhookController;
use App\Http\Controllers\Api\WorkspaceController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/webhooks/todoist', WebhookController::class)->middleware('throttle:300,1');
    Route::get('/health', fn () => response()->json(['status' => 'ok', 'version' => config('app.version', '0.1.0'), 'time' => now()->toIso8601String()]));
    Route::middleware('auth')->group(function () {
        Route::get('/me', [SessionController::class, 'show']);
        Route::get('/todoist/status', [TodoistController::class, 'status']);
        Route::get('/todoist/projects', [TodoistController::class, 'projects']);
        Route::post('/todoist/project', [TodoistController::class, 'selectProject']);
        Route::put('/tasks/{taskId}/dates', [TaskController::class, 'updateDates']);
        Route::get('/workspace', [WorkspaceController::class, 'show'])->middleware('throttle:120,1');
        Route::post('/schedule/simulate', ScheduleSimulationController::class)->middleware('throttle:30,1');
    });
});
