<?php

use App\Http\Controllers\Api\ObservabilityController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\SessionController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::middleware('web')->group(function () {
        Route::get('/health', [ObservabilityController::class, 'health']);
        Route::get('/ready', [ObservabilityController::class, 'readiness']);
        Route::get('/metrics', [ObservabilityController::class, 'metrics'])->middleware('throttle:30,1');
        Route::middleware('auth')->group(function () {
            Route::get('/me', [SessionController::class, 'show']);
            Route::get('/sessions', [SessionController::class, 'index']);
            Route::delete('/sessions/{sessionId}', [SessionController::class, 'destroy']);
            Route::get('/projects', [ProjectController::class, 'index']);
            Route::post('/projects', [ProjectController::class, 'store']);
            Route::get('/projects/{projectId}/workspace', [ProjectController::class, 'workspace']);
            Route::post('/projects/{projectId}/sections', [ProjectController::class, 'createSection']);
            Route::put('/projects/{projectId}/sections/{sectionId}', [ProjectController::class, 'updateSection']);
            Route::post('/projects/{projectId}/structure/move', [ProjectController::class, 'moveStructureItem']);
            Route::post('/projects/{projectId}/tasks', [ProjectController::class, 'createTask']);
            Route::post('/projects/{projectId}/tasks/{taskId}/duplicate', [ProjectController::class, 'duplicateTask']);
            Route::put('/projects/{projectId}/tasks/{taskId}', [ProjectController::class, 'updateTask']);
            Route::patch('/projects/{projectId}/tasks/{taskId}/completion', [ProjectController::class, 'setTaskCompletion']);
            Route::post('/projects/{projectId}/tasks/{taskId}/checklist', [ProjectController::class, 'createChecklistItem']);
            Route::put('/projects/{projectId}/tasks/{taskId}/checklist/{itemId}', [ProjectController::class, 'updateChecklistItem']);
            Route::put('/projects/{projectId}/tasks/{taskId}/checklist', [ProjectController::class, 'reorderChecklist']);
            Route::delete('/projects/{projectId}/tasks/{taskId}/checklist/{itemId}', [ProjectController::class, 'deleteChecklistItem']);
            Route::delete('/projects/{projectId}/tasks/{taskId}', [ProjectController::class, 'deleteTask']);
            Route::get('/projects/{projectId}/tasks/{taskId}/context', [ProjectController::class, 'taskContext']);
            Route::post('/projects/{projectId}/tasks/{taskId}/comments', [ProjectController::class, 'createComment']);
            Route::put('/projects/{projectId}/tasks/{taskId}/comments/{commentId}', [ProjectController::class, 'updateComment']);
            Route::delete('/projects/{projectId}/tasks/{taskId}/comments/{commentId}', [ProjectController::class, 'deleteComment']);
            Route::delete('/projects/{projectId}/sections/{sectionId}', [ProjectController::class, 'deleteSection']);
            Route::post('/projects/{projectId}/dependencies', [ProjectController::class, 'createDependency']);
            Route::put('/projects/{projectId}/dependencies/{dependencyId}', [ProjectController::class, 'updateDependency']);
            Route::delete('/projects/{projectId}/dependencies/{dependencyId}', [ProjectController::class, 'deleteDependency']);
            Route::post('/projects/{projectId}/people', [ProjectController::class, 'createPerson']);
            Route::put('/projects/{projectId}/people/{personId}', [ProjectController::class, 'updatePerson']);
            Route::post('/projects/{projectId}/people/{personId}/block', [ProjectController::class, 'blockPerson']);
            Route::delete('/projects/{projectId}/people/{personId}', [ProjectController::class, 'deletePerson']);
            Route::post('/projects/{projectId}/invitations', [ProjectController::class, 'inviteMember']);
            Route::post('/projects/{projectId}/invitations/{invitationId}/resend', [ProjectController::class, 'resendInvitation']);
            Route::delete('/projects/{projectId}/invitations/{invitationId}', [ProjectController::class, 'revokeInvitation']);
            Route::get('/projects/{projectId}/members', [ProjectController::class, 'members']);
            Route::put('/projects/{projectId}/members/{memberId}', [ProjectController::class, 'updateMember']);
            Route::delete('/projects/{projectId}/members/{memberId}', [ProjectController::class, 'removeMember']);
            Route::delete('/projects/{projectId}', [ProjectController::class, 'deleteProject']);
            Route::get('/invitations', [ProjectController::class, 'pendingInvitations']);
            Route::post('/invitations/{invitationId}/accept', [ProjectController::class, 'acceptInvitation']);
        });
    });
});
