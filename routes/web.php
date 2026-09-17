<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\WorkspaceController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => view('app'))->name('login');
Route::get('/api/session', fn (Request $request) => ['user' => $request->user(), 'csrf' => csrf_token()]);
Route::middleware(['guest', 'throttle:10,1'])->group(function () {
    Route::post('/api/login', [AuthController::class, 'login']);
    Route::post('/api/register', [AuthController::class, 'register']);
});
Route::middleware('auth')->prefix('api')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/workspace', [WorkspaceController::class, 'index']);
    Route::post('/tasks', [WorkspaceController::class, 'store']);
    Route::put('/tasks/{task}', [WorkspaceController::class, 'update']);
    Route::patch('/tasks/{task}/completion', [WorkspaceController::class, 'toggle']);
    Route::delete('/tasks/{task}', [WorkspaceController::class, 'destroy']);
    Route::post('/projects', [WorkspaceController::class, 'storeProject']);
    Route::delete('/projects/{project}', [WorkspaceController::class, 'destroyProject']);
});
