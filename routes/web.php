<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\BotInviteController;
use App\Http\Controllers\BotMsgTemplateController;
use App\Http\Controllers\MsgSetController;
use App\Http\Controllers\BotWebhookController;

// Главная
Route::get('/', function () {
    return view('welcome');
});

// Группа Dashboard
Route::middleware(['auth', 'verified'])->prefix('dashboard')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('/', [DashboardController::class, 'store']);
    Route::delete('/bot/{bot}', [DashboardController::class, 'destroy'])->name('bot.destroy');

    // Группа чатов (оставь тут!)
    Route::get('/chats', [ChatController::class, 'index'])->name('dashboard.chats');
    Route::post('/chats/send/{user_id}', [ChatController::class, 'send'])->name('dashboard.chats.send');
    Route::get('/chats/messages', [ChatController::class, 'chatMessages'])->name('dashboard.chats.messages');
    Route::get('/ajax/chat-messages', [ChatController::class, 'ajaxChatMessages'])->name('dashboard.ajax.chatMessages');
    Route::get('/chats/ajax-list', [ChatController::class, 'ajaxChatList'])->name('dashboard.chats.ajax-list');
    Route::post('/chats/read/{userId}', [ChatController::class, 'markAsRead'])->name('dashboard.chats.read');
    Route::delete('/chats/message/{id}', [ChatController::class, 'delete'])->name('chats.delete');
    Route::post('/chats/message/{id}/edit', [ChatController::class, 'edit'])->name('chats.edit');

    // --- Наборы сообщений (старый контроллер, для /dashboard/msg-set/...) ---
    Route::get('/msg-set/{bot}', [MsgSetController::class, 'index'])->name('dashboard.msg-set');
    //Route::get('/msg-set/{bot}/edit/{msg?}', [MsgSetController::class, 'edit'])->name('msg-set.edit');
    //Route::post('/msg-set/{bot}/edit/{msg?}', [MsgSetController::class, 'update'])->name('msg-set.update');

    // --- Новый контроллер BotMsgTemplateController ---
    Route::get('/msg-set/{bot}', [BotMsgTemplateController::class, 'index'])->name('msg-sets.index');
    Route::get('/msg-set/{bot}/create', [BotMsgTemplateController::class, 'create'])->name('msg-sets.create');
    Route::post('/msg-set/{bot}/store', [BotMsgTemplateController::class, 'store'])->name('msg-sets.store');

    // Обновление и редактирование — оставляем однозначные маршруты с методами PUT и GET
//    Route::get('/msg-set/{bot}/edit/{template_id?}', [BotMsgTemplateController::class, 'edit'])->name('msg-sets.edit');
//    Route::put('/msg-set/{bot}/edit/{msg}', [BotMsgTemplateController::class, 'update'])->name('msg-sets.update');
    // вместо {msg} — {template}
    Route::get ( '/msg-set/{bot}/edit/{template?}', [BotMsgTemplateController::class,'edit']  )->name('msg-sets.edit');
    Route::put ( '/msg-set/{bot}/edit/{template}' , [BotMsgTemplateController::class,'update'])->name('msg-sets.update');
    Route::get ( '/dashboard/msg-set/{bot}/edit/{template_id?}', [BotMsgTemplateController::class, 'edit'])->name('msg-sets.edit');


    // Дополнительные POST маршруты на update (если нужны) — оставь, но избегай дублирования с PUT
    Route::post('/msg-set/{bot}/update/{msg}', [BotMsgTemplateController::class, 'update'])->name('msg-sets.update-post');
    Route::post('/msg-set/{bot}/update/{template_id}', [BotMsgTemplateController::class, 'update'])->name('msg-sets.update-post-alt');

});

// При отправке вебхука на адрес /bot/{bot}/webhook
Route::post('/bot/{bot}/webhook', BotWebhookController::class)->name('bot.webhook');

// Остальные страницы профиля и настройки (оставляй)
Route::middleware(['auth'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::get('/statistics', function () { return view('statistics'); });
    Route::get('/security', function () { return view('security'); });
    Route::get('/faq', function () { return view('faq'); });

    // API для Vue, но через сессию:
    Route::post('/chats/{userId}/messages', [ChatController::class, 'sendMessage'])->name('chats.send');
});

// Приглашения к боту (оставляй как есть)
Route::middleware(['auth'])->group(function () {
    Route::post('/bots/invite/join', [BotInviteController::class, 'join'])->name('bots.invite.join');
    Route::post('/bots/invite/generate', [BotInviteController::class, 'generate'])->name('bots.invite.generate');
});

require __DIR__.'/auth.php';
