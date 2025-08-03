<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Bot;
use App\Models\ChatMessage;
use App\Models\User;
use App\Models\TgChatUser;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;

class ChatController extends Controller
{
    // Главная страница с чатами и фильтрами
    public function index(Request $request)
    {
        $userId = auth()->id();

        $bots = Bot::where('is_active', 1)
            ->where('user_id', $userId)
            ->get();

        $operators = User::where('role', 'operator')->get();

        $selectedBot = $request->bot_id ? Bot::find($request->bot_id) : null;

        $query = ChatMessage::query();

        if ($selectedBot) {
            $query->where('bot_token', $selectedBot->bot_token);
        }

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('username', 'like', '%' . $request->search . '%')
                    ->orWhere('telegram_user_id', $request->search);
            });
        }

        // Группируем пользователей Telegram с их сообщениями
        $chatUsers = TgChatUser::with(['messages' => function($q) {
            $q->orderByDesc('created_at');
        }])
            ->whereHas('messages')
            ->orderByDesc('updated_at')
            ->get()
            ->unique('user_id')
            ->values();

        $openedChat = null;
        if ($request->has('user_id')) {
            $openedChat = TgChatUser::with(['messages' => function($q) {
                $q->orderByDesc('created_at');
            }])->where('id', $request->user_id)->first();
        }

        return view('dashboard.chats', compact('bots', 'operators', 'selectedBot', 'chatUsers', 'openedChat'));
    }

    public function send(Request $request, $user_id)
    {
        $validated = $request->validate([
            'text' => 'required|string|max:1000',
        ]);

        $chatUser = TgChatUser::findOrFail($user_id);
        $bot = Bot::findOrFail($chatUser->bot_id);

        ChatMessage::create([
            'chat_id' => $chatUser->user_id,
            'telegram_user_id' => $chatUser->user_id,
            'text' => $validated['text'],
            'is_operator' => true,
            'bot_token' => $bot->bot_token,
        ]);

        return redirect()->route('dashboard.chats', ['user_id' => $user_id]);
    }

    // ==== ОСТАВЬ ЭТИ ДВА API-МЕТОДА ДЛЯ VUE ====

    // API: Получить список сообщений (для Vue)
    public function getMessages($userId)
    {
        $messages = \App\Models\ChatMessage::where('telegram_user_id', $userId)
            ->orderBy('created_at')
            ->get()
            ->map(function($msg) {
                $msg->is_operator = ($msg->is_operator ?? 0) ? true : false;
                return $msg;
            });

        return response()->json($messages);
    }

    // API: Отправить новое сообщение (для Vue)
    public function sendMessage(Request $request, $userId)
    {
        $validated = $request->validate([
            'text' => 'required|string|max:1000',
        ]);

        // 1) Находим чат-пользователя и токен его бота
        $chatUser = TgChatUser::findOrFail($userId);
        $bot      = Bot::findOrFail($chatUser->bot_id);

        // 2) Создаём запись в БД, теперь с username оператора
        $msg = ChatMessage::create([
            'bot_token'         => $bot->bot_token,
            'chat_id'           => $chatUser->user_id,
            'telegram_user_id'  => $chatUser->user_id,
            'username'          => Auth::user()->name,      // <-- сохраняем имя оператора
            'text'              => $validated['text'],
            'is_read'           => 0,
            'is_operator'       => true,
            'created_at'        => now(),
        ]);

        // 3) Отправляем сообщение реальному пользователю Telegram
        Http::post("https://api.telegram.org/bot{$bot->bot_token}/sendMessage", [
            'chat_id' => $chatUser->user_id,
            'text'    => $validated['text'],
        ]);

        // 4) Возвращаем JSON для Vue
        $msg->is_operator = true;
        return response()->json($msg);
    }

    public function getUserInfo($userId)
    {
        $user = \App\Models\TgChatUser::where('user_id', $userId)->first();
        // если не нашли — возвращаем null, иначе — массив полей
        return response()->json($user ? $user->toArray() : null);
    }

    public function markAsRead($userId)
    {
        \App\Models\ChatMessage::where('telegram_user_id', $userId)
            ->where('is_read', 0)
            ->update([
                'is_read'    => 1,
                'updated_at' => now(),
            ]);

        return response()->json(['ok' => true]);
    }
}
