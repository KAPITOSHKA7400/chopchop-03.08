<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\BotMsgTemplateController;
use App\Http\Controllers\Api\BotMsgTemplateApiController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

/**
 * ВАЖНО:
 * В api.php НИГДЕ не пишем ручками префикс /api.
 * Laravel сам добавляет /api ко всем этим маршрутам.
 */

// Авторизованные примеры (не критично для чата)
Route::middleware('auth')->group(function () {
    Route::get('chat/{userId}', [ChatController::class, 'getMessages']);
    Route::post('chat/{userId}', [ChatController::class, 'sendMessage']);
});

// --- Чат API для Vue ---
Route::get   ('chats/{userId}/messages', [ChatController::class, 'getMessages']);
Route::get   ('chats/{userId}/info',     [ChatController::class, 'getUserInfo']);
Route::post  ('chats/{userId}/messages', [ChatController::class, 'sendMessage']);

// Редактирование / Удаление (ИМЕННО ТАК, POST, без слэша и без "api" в начале)
Route::post  ('chats/{userId}/messages/{messageId}/update', [ChatController::class, 'updateOperatorMessage']);
Route::post  ('chats/{userId}/messages/{messageId}/delete', [ChatController::class, 'deleteOperatorMessage']);

// Список чатов
Route::get   ('chats/list', [ChatController::class, 'list']);

// Шаблоны (оставляем один рабочий index — второй раньше перетирал первый)
Route::get   ('bots/{bot}/templates', [BotMsgTemplateApiController::class, 'index']);

// Отправка шаблона в чат (ТОЛЬКО ЭТО — без «/api/» в пути!)
Route::post  ('chats/{userId}/send-template', [ChatController::class, 'sendTemplate']);

// Загрузка файлов
Route::post  ('chats/upload', [ChatController::class, 'storeFiles']);

//// (Необязательный пример) шаблоны по пользователю
//Route::get('chats/{user}/templates', function (\App\Models\ChatUser $user) {
//    return \App\Models\BotMsgTemplate::where('bot_id', $user->bot_id)
//        ->where('type', 'custom')
//        ->select('id','title','text','type')
//        ->get();
//});
