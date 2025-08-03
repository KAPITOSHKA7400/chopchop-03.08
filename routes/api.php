<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ChatController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Для твоей авторизации
Route::middleware('auth')->group(function () {
    Route::get('/chat/{userId}', [ChatController::class, 'getMessages']);
    Route::post('/chat/{userId}', [ChatController::class, 'sendMessage']);
});

// -----------------
// Для Vue компонента, вот ЭТИ строки обязательно нужны:
Route::get('/chats/{userId}/messages', [ChatController::class, 'getMessages']);
Route::get('/chats/{userId}/info',     [ChatController::class, 'getUserInfo']);
Route::post('/chats/{userId}/messages', [ChatController::class, 'sendMessage']);

