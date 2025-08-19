<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\BotMsgTemplateController;
use App\Http\Controllers\Api\BotMsgTemplateApiController;

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

// Для Vue обновление списка чатов:
Route::get('/chats/list', [ChatController::class, 'list']);

Route::get('/bots/{bot}/templates', [BotMsgTemplateController::class, 'apiTemplates']);
Route::get('/bots/{bot}/templates', [BotMsgTemplateApiController::class, 'index']);
Route::get('/templates/{id}', [\App\Http\Controllers\TemplateApiController::class, 'show']);
Route::post('/chats/{chat}/send-template', [App\Http\Controllers\ChatController::class, 'sendTemplate']);
Route::post('/api/chats/{userId}/send-template', [ChatController::class, 'sendTemplate']);
Route::post('/chats/upload', [ChatController::class, 'storeFiles']);



Route::get('chats/{user}/templates', function(\App\Models\ChatUser $user){
    return \App\Models\BotMsgTemplate::where('bot_id', $user->bot_id)
        ->where('type', 'custom')
        ->select('id','title','text','type')
        ->get();
});


