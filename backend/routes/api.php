<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Middleware\AdminKeyMiddleware;
use Illuminate\Support\Facades\Route;

Route::post('/chat', [ChatController::class, 'store']);
Route::get('/chat/suggestions', [ChatController::class, 'suggestions']);
Route::get('/chat/session/{sessionId}', [ChatController::class, 'sessionHistory']);
Route::get('/health', [ChatController::class, 'health']);

Route::middleware(AdminKeyMiddleware::class)->prefix('admin')->group(function () {
    Route::get('/logs', [AdminController::class, 'logs']);
    Route::get('/stats', [AdminController::class, 'stats']);
});
