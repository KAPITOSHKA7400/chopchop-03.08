<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\BotInviteController;

Route::get('/', function () {
    return view('welcome');
});

// Группа Dashboard
Route::middleware(['auth', 'verified'])->prefix('dashboard')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('/', [DashboardController::class, 'store']);
    Route::delete('/bot/{bot}', [DashboardController::class, 'destroy'])->name('bot.destroy');
});

// Группа чатов
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard/chats', [ChatController::class, 'index'])->name('dashboard.chats');
    Route::post('/dashboard/chats/send/{user_id}', [ChatController::class, 'send'])->name('dashboard.chats.send');

    // AJAX/partial
    Route::get('/dashboard/chats/messages', [ChatController::class, 'chatMessages'])->name('dashboard.chats.messages');
    Route::get('/dashboard/ajax/chat-messages', [ChatController::class, 'ajaxChatMessages'])->name('dashboard.ajax.chatMessages');
//    Route::get('/dashboard/chats/list', [ChatController::class, 'chatList'])->name('dashboard.chats.list');
    Route::get('/dashboard/chats/ajax-list', [ChatController::class, 'ajaxChatList'])->name('dashboard.chats.ajax-list');

    Route::post('/dashboard/chats/read/{userId}', [ChatController::class, 'markAsRead'])
        ->name('dashboard.chats.read');

    // (если используешь, иначе можно убрать)
    Route::delete('/dashboard/chats/message/{id}', [ChatController::class, 'delete'])->name('chats.delete');
    Route::post('/dashboard/chats/message/{id}/edit', [ChatController::class, 'edit'])->name('chats.edit');
});

// Остальные страницы профиля и настройки (оставь как есть)
Route::middleware(['auth'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::get('/statistics', function () { return view('statistics'); });
    Route::get('/security', function () { return view('security'); });
    Route::get('/faq', function () { return view('faq'); });

    // API для Vue, но через сессию:
    Route::post('/chats/{userId}/messages', [ChatController::class, 'sendMessage'])
        ->name('chats.send');
});

// Приглашения к боту (оставь как есть)
Route::middleware(['auth'])->group(function () {
    Route::post('/bots/invite/join', [BotInviteController::class, 'join'])->name('bots.invite.join');
    Route::post('/bots/invite/generate', [BotInviteController::class, 'generate'])->name('bots.invite.generate');
});

require __DIR__.'/auth.php';

