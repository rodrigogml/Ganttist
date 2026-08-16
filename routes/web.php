<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\TodoistOAuthController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/request-link', [AuthController::class, 'requestLink'])->middleware('throttle:5,1');
Route::post('/auth/verify', [AuthController::class, 'verify'])->middleware('throttle:10,1');
Route::post('/auth/logout', [AuthController::class, 'logout'])->middleware('auth');
Route::get('/oauth/todoist/redirect', [TodoistOAuthController::class, 'redirect'])->middleware('auth');
Route::get('/oauth/todoist/callback', [TodoistOAuthController::class, 'callback']);

Route::view('/{path?}', 'app')->where('path', '^(?!api).*$');
