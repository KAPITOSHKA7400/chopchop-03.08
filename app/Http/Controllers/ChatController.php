<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Bot;
use App\Models\ChatMessage;
use App\Models\User;
use App\Models\TgChatUser;
use App\Models\BotMsgTemplate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
//use App\Models\ChatMessage;
use App\Models\ChatMessageFile;

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

        // Список чатов для Vue / Blade
        $chatUsers = TgChatUser::with(['messages' => fn($q) => $q->orderByDesc('created_at')])
            ->whereHas('messages')
            ->orderByDesc('updated_at')
            ->get()
            ->unique('user_id')
            ->values();

        $openedChat = null;
        if ($request->filled('user_id')) {
            $openedChat = TgChatUser::with(['messages' => fn($q) => $q->orderByDesc('created_at')])
                ->where('user_id', $request->user_id)
                ->first();
        }
        if (!$openedChat && $chatUsers->isNotEmpty()) {
            $openedChat = $chatUsers->first();
        }

        return view('dashboard.chats', compact(
            'bots', 'operators', 'selectedBot',
            'chatUsers', 'openedChat'
        ));
    }

    // Сохранить сообщение из формы оператора (Blade)
    public function send(Request $request, $user_id)
    {
        $validated = $request->validate([
            'text' => 'required|string|max:1000',
        ]);

        $chatUser = TgChatUser::findOrFail($user_id);
        $bot = Bot::findOrFail($chatUser->bot_id);

        ChatMessage::create([
            'bot_token'        => $bot->bot_token,
            'chat_id'          => $chatUser->user_id,
            'telegram_user_id' => $chatUser->user_id,
            'username'         => Auth::user()->name,
            'text'             => $validated['text'],
            'is_operator'      => true,
            'is_read'          => 0,
        ]);

        return redirect()->route('dashboard.chats', ['user_id' => $user_id]);
    }

    // ==== API для Vue ====

    // Получить список сообщений чата
    public function getMessages($userId)
    {
        $messages = \App\Models\ChatMessage::where('telegram_user_id', $userId)
            ->with(['files' => function ($q) {
                $q->select('id','chat_message_id','file_name','file_path','mime_type','size','created_at');
            }])
            ->orderBy('created_at')
            ->get();

        $data = $messages->map(function ($m) {
            $files = $m->files->map(function ($f) {
                return [
                    'id'   => $f->id,
                    'url'  => $f->url,          // аксессор из модели
                    'name' => $f->file_name,
                    'type' => $f->mime_type,
                    'size' => $f->size,
                ];
            })->values()->all();

            return [
                'id'               => $m->id,
                'bot_token'        => $m->bot_token,
                'chat_id'          => $m->chat_id,
                'telegram_user_id' => $m->telegram_user_id,
                'username'         => $m->username,
                'text'             => $m->text,
                'is_operator'      => (bool) ($m->is_operator ?? 0),
                'is_auto'          => (bool) ($m->is_auto ?? 0),
                'created_at'       => optional($m->created_at)->toIso8601String(),
                'updated_at'       => optional($m->updated_at)->toIso8601String(),
                'files'            => $files,
            ];
        })->values();

        return response()->json($data);
    }

    // Отправить сообщение от оператора (Vue)
    public function sendMessage(Request $request, $userId)
    {
        $validated = $request->validate([
            'text' => 'required|string|max:1000',
        ]);

        $chatUser = TgChatUser::findOrFail($userId);
        $bot      = Bot::findOrFail($chatUser->bot_id);

        $msg = ChatMessage::create([
            'bot_token'        => $bot->bot_token,
            'chat_id'          => $chatUser->user_id,
            'telegram_user_id' => $chatUser->user_id,
            'username'         => Auth::user()->name,
            'text'             => $validated['text'],
            'is_operator'      => true,
            'is_read'          => 0,
            'created_at'       => now(),
        ]);

        // Отправляем в Telegram
        Http::post("https://api.telegram.org/bot{$bot->bot_token}/sendMessage", [
            'chat_id' => $chatUser->user_id,
            'text'    => $validated['text'],
        ]);

        $msg->is_operator = true;
        return response()->json($msg);
    }

    // Список чатов для Vue
    public function list()
    {
        $chatUsers = TgChatUser::whereHas('messages')
            ->with(['messages' => fn($q) => $q->orderByDesc('created_at')])
            ->select('*')
            ->addSelect(['last_message_time' => ChatMessage::select('created_at')
                ->whereColumn('telegram_user_id', 'tg_chat_users.user_id')
                ->orderByDesc('created_at')
                ->limit(1)
            ])
            ->orderByDesc('last_message_time')
            ->get();

        return response()->json($chatUsers);
    }

    // Инфо о пользователе в чате для Vue
    public function getUserInfo($userId)
    {
        $user = TgChatUser::where('user_id', $userId)->first();
        return response()->json($user ? $user->toArray() : null);
    }

    // Отметить сообщения прочитанными
    public function markAsRead($userId)
    {
        ChatMessage::where('telegram_user_id', $userId)
            ->where('is_read', 0)
            ->update([
                'is_read'    => 1,
                'updated_at' => now(),
            ]);

        return response()->json(['ok' => true]);
    }

    // Отправить шаблон (Vue «СМС из шаблона»)
    public function sendTemplate(Request $request, $userId)
    {
        $request->validate([
            'template_id' => 'required|integer|exists:bot_msg_templates,id',
        ]);

        $template = BotMsgTemplate::with('files')->findOrFail($request->template_id);
        $chatUser = TgChatUser::findOrFail($userId);
        $bot      = Bot::findOrFail($chatUser->bot_id);
        $chatId   = $chatUser->user_id;
        $label    = 'Шаблон – ' . $template->title;

        // 1) Отправляем текст
        Http::post("https://api.telegram.org/bot{$bot->bot_token}/sendMessage", [
            'chat_id' => $chatId,
            'text'    => $template->body,
        ]);

        // 2) Отправляем файлы
        $base = "https://api.telegram.org/bot{$bot->bot_token}/";
        foreach ($template->files as $f) {
            $path = storage_path("app/public/{$f->file_path}");
            $param = match (true) {
                str_starts_with($f->file_mime, 'image/') => 'photo',
                str_starts_with($f->file_mime, 'video/') => 'video',
                str_starts_with($f->file_mime, 'audio/') => 'audio',
                default => 'document',
            };
            Http::attach($param, file_get_contents($path), basename($path))
                ->post($base . "send" . ucfirst($param), ['chat_id' => $chatId]);
        }

        // 3) Сохраняем в БД и собираем все новые сообщения в массив
        $created = [];

        // текстовое сообщение
        $msg = ChatMessage::create([
            'bot_token'        => $bot->bot_token,
            'chat_id'          => $chatId,
            'telegram_user_id' => $chatId,
            'username'         => $label,
            'text'             => $template->body,
            'is_operator'      => true,
            'created_at'       => now(),
        ]);
        $created[] = $msg;

        // для каждого файла создаём запись сообщения + запись файла
        foreach ($template->files as $f) {
            $fileMsg = ChatMessage::create([
                'bot_token'        => $bot->bot_token,
                'chat_id'          => $chatId,
                'telegram_user_id' => $chatId,
                'username'         => $label,
                'text'             => '',             // без текста
                // 'attachment_path'  => $f->file_path, // поля такого нет в таблице chat_message
                'is_operator'      => true,
                'created_at'       => now(),
            ]);

            // Привязываем файл к созданному сообщению
            ChatMessageFile::create([
                'chat_message_id' => $fileMsg->id,
                'file_name'       => basename($f->file_path),
                'file_path'       => $f->file_path,                      // storage/app/public/...
                'mime_type'       => $f->file_mime ?? $f->file_type ?? null,
                'size'            => $f->file_size ?? null,
            ]);

            $created[] = $fileMsg;
        }

        // 4) Возвращаем именно эти новые записи
        return response()->json($created);
    }

    public function storeFiles(Request $request)
    {
        // Логирование входящих данных
        \Log::info('Входящие данные файла:', ['data' => $request->all()]);

        // dd($request->all());  // Отключено: прерывало выполнение

        try {
            // Проверка на ошибки в файле
            $request->validate([
                'file' => 'required|mimes:jpg,png,pdf|max:5000',
                'chat_message_id' => 'required|integer|exists:chat_message,id',
            ]);

            $file = $request->file('file');
            // dd($file);  // Отключено: прерывало выполнение

            // сохраняем в публичный диск, чтобы Storage::url работал через /storage
            $filePath = $file->store('chat_files', 'public');

            $chatMessageFile = ChatMessageFile::create([
                'chat_message_id' => $request->chat_message_id,
                'file_name'       => $file->getClientOriginalName(),
                'file_path'       => $filePath,
                'mime_type'       => $file->getMimeType(),
                'size'            => $file->getSize(),
            ]);

            // Логирование успешного сохранения
            \Log::info('Файл успешно сохранен в базе данных:', ['file' => $chatMessageFile]);

            return response()->json(['message' => 'Файл успешно загружен!']);
        } catch (\Exception $e) {
            // Логирование ошибок
            \Log::error('Ошибка при загрузке файла:', ['error' => $e->getMessage()]);

            return response()->json(['message' => 'Ошибка при загрузке файла'], 500);
        }
    }
}
