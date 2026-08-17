<?php

use App\Http\Controllers\Api\ScheduleSimulationController;
use App\Http\Controllers\Api\ScheduleApplyController;
use App\Http\Controllers\Api\DependencyController;
use App\Http\Controllers\Api\EventStreamController;
use App\Http\Controllers\Api\SessionController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\TodoistController;
use App\Http\Controllers\Api\WebhookController;
use App\Http\Controllers\Api\WorkspaceController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/webhooks/todoist', WebhookController::class)->middleware('throttle:300,1');
    Route::middleware('web')->group(function () {
        Route::get('/health', fn () => response()->json(['status' => 'ok', 'version' => config('app.version', '0.1.0'), 'time' => now()->toIso8601String()]));
        Route::middleware('auth')->group(function () {
            Route::get('/me', [SessionController::class, 'show']);
            Route::get('/events', EventStreamController::class);
            Route::get('/todoist/status', [TodoistController::class, 'status']);
            Route::get('/todoist/projects', [TodoistController::class, 'projects']);
            Route::post('/todoist/project', [TodoistController::class, 'selectProject']);
            Route::delete('/todoist/integration', [TodoistController::class, 'disconnect']);
            Route::put('/tasks/{taskId}/dates', [TaskController::class, 'updateDates']);
            Route::put('/tasks/{taskId}', [TaskController::class, 'update']);
            Route::get('/workspace', [WorkspaceController::class, 'show'])->middleware('throttle:120,1');
            Route::post('/schedule/simulate', ScheduleSimulationController::class)->middleware('throttle:30,1');
            Route::post('/schedule/apply', ScheduleApplyController::class)->middleware('throttle:10,1');
            Route::get('/dependencies', [DependencyController::class, 'index']);
            Route::post('/dependencies', [DependencyController::class, 'store']);
            Route::delete('/dependencies/{id}', [DependencyController::class, 'destroy']);
        });
    });
});
