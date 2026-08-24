<?php

use App\Http\Controllers\Api\AuditController;
use App\Http\Controllers\Api\AutomationSettingsController;
use App\Http\Controllers\Api\CalendarController;
use App\Http\Controllers\Api\DependencyController;
use App\Http\Controllers\Api\EventStreamController;
use App\Http\Controllers\Api\ObservabilityController;
use App\Http\Controllers\Api\ScheduleApplyController;
use App\Http\Controllers\Api\ScheduleOperationController;
use App\Http\Controllers\Api\ScheduleSimulationController;
use App\Http\Controllers\Api\SessionController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\TodoistController;
use App\Http\Controllers\Api\WebhookController;
use App\Http\Controllers\Api\WorkspaceController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/webhooks/todoist', WebhookController::class)->middleware('throttle:300,1');
    Route::middleware('web')->group(function () {
        Route::get('/health', [ObservabilityController::class, 'health']);
        Route::get('/ready', [ObservabilityController::class, 'readiness']);
        Route::get('/metrics', [ObservabilityController::class, 'metrics'])->middleware('throttle:30,1');
        Route::middleware('auth')->group(function () {
            Route::get('/me', [SessionController::class, 'show']);
            Route::get('/sessions', [SessionController::class, 'index']);
            Route::delete('/sessions/{sessionId}', [SessionController::class, 'destroy']);
            Route::get('/events', EventStreamController::class);
            Route::get('/audit-events', [AuditController::class, 'index']);
            Route::get('/todoist/status', [TodoistController::class, 'status']);
            Route::post('/todoist/sync', [TodoistController::class, 'sync'])->middleware('throttle:6,1');
            Route::get('/todoist/projects', [TodoistController::class, 'projects']);
            Route::post('/todoist/project', [TodoistController::class, 'selectProject']);
            Route::delete('/todoist/integration', [TodoistController::class, 'disconnect']);
            Route::post('/tasks', [TaskController::class, 'store']);
            Route::post('/tasks/{taskId}/deletion-preview', [TaskController::class, 'deletionPreview']);
            Route::delete('/tasks/{taskId}', [TaskController::class, 'destroy']);
            Route::put('/tasks/{taskId}/dates', [TaskController::class, 'updateDates']);
            Route::put('/tasks/{taskId}/completion-date', [TaskController::class, 'updateCompletionDate']);
            Route::patch('/tasks/{taskId}/completion', [TaskController::class, 'setCompletion']);
            Route::put('/tasks/{taskId}', [TaskController::class, 'update']);
            Route::get('/tasks/{taskId}/editor-context', [TaskController::class, 'editorContext']);
            Route::post('/tasks/{taskId}/comments', [TaskController::class, 'createComment']);
            Route::put('/tasks/{taskId}/comments/{commentId}', [TaskController::class, 'updateComment']);
            Route::get('/workspace', [WorkspaceController::class, 'show'])->middleware('throttle:120,1');
            Route::get('/calendar', [CalendarController::class, 'show']);
            Route::post('/calendar/simulate', [CalendarController::class, 'simulate'])->middleware('throttle:30,1');
            Route::put('/calendar', [CalendarController::class, 'update'])->middleware('throttle:20,1');
            Route::get('/settings/automation', [AutomationSettingsController::class, 'show']);
            Route::put('/settings/automation', [AutomationSettingsController::class, 'update'])->middleware('throttle:20,1');
            Route::post('/schedule/simulate', ScheduleSimulationController::class)->middleware('throttle:30,1');
            Route::post('/schedule/apply', ScheduleApplyController::class)->middleware('throttle:10,1');
            Route::get('/schedule/operations/{operationId}', [ScheduleOperationController::class, 'show']);
            Route::get('/dependencies', [DependencyController::class, 'index']);
            Route::post('/dependencies', [DependencyController::class, 'store']);
            Route::delete('/dependencies/{id}', [DependencyController::class, 'destroy']);
        });
    });
});
