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
use Illuminate\Support\Facades\Storage;
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
        $messages = ChatMessage::where('telegram_user_id', $userId)
            ->with(['files' => function ($q) {
                $q->select('id','chat_message_id','file_name','file_path','mime_type','size','created_at','tg_message_id');
            }])
            ->orderBy('created_at')
            ->get();

        $data = $messages->map(function ($m) {
            $files = $m->files->map(function ($f) {
                return [
                    'id'            => $f->id,
                    'url'           => $f->url,          // аксессор из модели
                    'name'          => $f->file_name,
                    'type'          => $f->mime_type,
                    'size'          => $f->size,
                    'tg_message_id' => $f->tg_message_id ?? null,
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
                // нужно фронту для кнопок и бейджей
                'can_modify'       => (bool) ($m->is_operator && !$m->is_auto && !($m->is_deleted ?? 0)),
                'is_deleted'       => (bool) ($m->is_deleted ?? 0),
                'is_edited'        => (bool) ($m->is_edited ?? 0),
                'tg_message_id'    => $m->tg_message_id ?? null,
            ];
        })->values();

        return response()->json($data);
    }

    /**
     * Отправить сообщение от оператора (Vue).
     * multipart/form-data:
     *  - text: nullable|string
     *  - files[]: массив файлов (любых типов)
     */
    public function sendMessage(Request $request, $userId)
    {
        $request->validate([
            'text'     => ['nullable','string','max:10000'],
            'files'    => ['nullable','array'],
            'files.*'  => ['file','max:51200'], // 50MB на файл
        ]);

        $chatUser = TgChatUser::findOrFail($userId);
        $bot      = Bot::findOrFail($chatUser->bot_id);
        $chatId   = (int) $chatUser->user_id;

        // Создаём сообщение (даже без текста — для привязки файлов)
        $msg = new ChatMessage();
        $msg->bot_token        = $bot->bot_token;
        $msg->chat_id          = $chatId;
        $msg->telegram_user_id = $chatUser->user_id;
        $msg->username         = Auth::user()->name ?? 'Оператор';
        $msg->text             = (string) ($request->input('text') ?? '');
        $msg->is_operator      = 1;
        $msg->is_auto          = 0;
        $msg->is_read          = 1;
        $msg->is_deleted       = 0;
        $msg->is_edited        = 0;
        $msg->created_at       = now();
        $msg->updated_at       = now();
        $msg->save();

        // 1) Отправляем текст (если есть) — сохраним tg_message_id
        if (trim($msg->text) !== '') {
            $tgId = $this->tgSendMessage($bot->bot_token, $chatId, $msg->text);
            if ($tgId) {
                $msg->tg_message_id = $tgId;
                $msg->save();
            }
        }

        $filesResponse = [];

        // 2) Файлы
        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $uploaded) {
                if (!$uploaded || !$uploaded->isValid()) continue;

                $dir  = 'chat_uploads/' . date('Y') . '/' . date('m');
                $path = $uploaded->store($dir, 'public'); // storage/app/public/...
                $mime = $uploaded->getMimeType() ?: 'application/octet-stream';
                $size = $uploaded->getSize();
                $name = $uploaded->getClientOriginalName() ?: basename($path);

                $fileRow = new ChatMessageFile();
                $fileRow->chat_message_id = $msg->id;
                $fileRow->file_name       = $name;
                $fileRow->file_path       = $path;
                $fileRow->mime_type       = $mime;
                $fileRow->size            = $size;
                $fileRow->save();

                // Отправка файла в ТГ, вернётся message_id для этого медиа
                $mid = $this->tgSendFileByMime($bot->bot_token, $chatId, $path, $mime, '');
                if ($mid) {
                    $fileRow->tg_message_id = $mid;
                    $fileRow->save();
                }

                $filesResponse[] = [
                    'id'            => $fileRow->id,
                    'name'          => $name,
                    'mime_type'     => $mime,
                    'file_path'     => $path,
                    'url'           => Storage::url($path),
                    'size'          => $size,
                    'tg_message_id' => $fileRow->tg_message_id ?? null,
                ];
            }
        }

        return response()->json([
            'id'               => $msg->id,
            'bot_token'        => $msg->bot_token,
            'chat_id'          => $msg->chat_id,
            'telegram_user_id' => $msg->telegram_user_id,
            'username'         => $msg->username,
            'text'             => $msg->text,
            'is_operator'      => true,
            'is_auto'          => false,
            'is_read'          => true,
            'is_deleted'       => false,
            'is_edited'        => false,
            'created_at'       => $msg->created_at,
            'files'            => $filesResponse,
            'can_modify'       => true,
            'tg_message_id'    => $msg->tg_message_id ?? null,
        ]);
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

        $created = [];

        // 1) Текст
        $tgId = $this->tgSendMessage($bot->bot_token, (int)$chatId, $template->body);

        $msg = ChatMessage::create([
            'bot_token'        => $bot->bot_token,
            'chat_id'          => $chatId,
            'telegram_user_id' => $chatId,
            'username'         => $label,
            'text'             => $template->body,
            'is_operator'      => true,
            'created_at'       => now(),
            'tg_message_id'    => $tgId,
            'is_deleted'       => 0,
            'is_edited'        => 0,
        ]);
        $created[] = $msg;

        // 2) Файлы
        foreach ($template->files as $f) {
            $mime    = $f->file_mime ?? $f->file_type ?? 'application/octet-stream';
            $tgFileId = $this->tgSendFileByMime($bot->bot_token, (int)$chatId, $f->file_path, $mime, '');

            $fileMsg = ChatMessage::create([
                'bot_token'        => $bot->bot_token,
                'chat_id'          => $chatId,
                'telegram_user_id' => $chatId,
                'username'         => $label,
                'text'             => '',
                'is_operator'      => true,
                'created_at'       => now(),
                'tg_message_id'    => $tgFileId,
                'is_deleted'       => 0,
                'is_edited'        => 0,
            ]);

            ChatMessageFile::create([
                'chat_message_id' => $fileMsg->id,
                'file_name'       => basename($f->file_path),
                'file_path'       => $f->file_path,
                'mime_type'       => $mime,
                'size'            => $f->file_size ?? null,
                // tg_message_id хранится в ChatMessage
            ]);

            $created[] = $fileMsg;
        }

        return response()->json($created);
    }

    public function storeFiles(Request $request)
    {
        \Log::info('Входящие данные файла:', ['data' => $request->all()]);

        try {
            $request->validate([
                'file' => 'required|mimes:jpg,png,pdf|max:5000',
                'chat_message_id' => 'required|integer|exists:chat_message,id',
            ]);

            $file = $request->file('file');

            $filePath = $file->store('chat_files', 'public');

            $chatMessageFile = ChatMessageFile::create([
                'chat_message_id' => $request->chat_message_id,
                'file_name'       => $file->getClientOriginalName(),
                'file_path'       => $filePath,
                'mime_type'       => $file->getMimeType(),
                'size'            => $file->getSize(),
            ]);

            \Log::info('Файл успешно сохранен в базе данных:', ['file' => $chatMessageFile]);

            return response()->json(['message' => 'Файл успешно загружен!']);
        } catch (\Exception $e) {
            \Log::error('Ошибка при загрузке файла:', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Ошибка при загрузке файла'], 500);
        }
    }

    // ---------------- ОПЕРАЦИИ РЕДАКТИРОВАНИЯ/УДАЛЕНИЯ ----------------

    // PATCH/POST: изменить текст/подпись сообщения оператора
    public function updateOperatorMessage(Request $request, $userId, $messageId)
    {
        $request->validate([
            'text' => ['required','string','max:10000'],
        ]);

        // ВАЖНО: ищем по уникальному id + is_operator (без where telegram_user_id)
        $msg = ChatMessage::where('id', $messageId)
            ->where('is_operator', 1)
            ->firstOrFail();

        // Берём параметры из самого сообщения — это исключает рассинхрон идентификаторов
        $chatId = (int) $msg->chat_id;
        $bot    = Bot::where('bot_token', $msg->bot_token)->firstOrFail();

        if (!empty($msg->tg_message_id)) {
            try {
                $hasFiles = $msg->files()->exists();
                if ($hasFiles) {
                    $this->tgEditCaption($bot->bot_token, $chatId, (int)$msg->tg_message_id, $request->text);
                } else {
                    $this->tgEditText($bot->bot_token,   $chatId, (int)$msg->tg_message_id, $request->text);
                }
            } catch (\Throwable $e) {
                \Log::warning('tg edit failed', ['err' => $e->getMessage(), 'message_id' => $msg->id]);
            }
        }

        $msg->text      = $request->text;
        $msg->is_edited = 1;
        $msg->save();

        return response()->json([
            'id'         => $msg->id,
            'text'       => $msg->text,
            'is_edited'  => true,
        ]);
    }

    // POST/DELETE: удалить сообщение оператора у пользователя
    public function deleteOperatorMessage(Request $request, $userId, $messageId)
    {
        // ВАЖНО: ищем по уникальному id + is_operator (без where telegram_user_id)
        $msg = ChatMessage::where('id', $messageId)
            ->where('is_operator', 1)
            ->firstOrFail();

        $chatId = (int) $msg->chat_id;
        $bot    = Bot::where('bot_token', $msg->bot_token)->firstOrFail();

        // 1) удалить само сообщение (если это текстовое/медиа-главное)
        if (!empty($msg->tg_message_id)) {
            try {
                $this->tgDeleteMessage($bot->bot_token, $chatId, (int)$msg->tg_message_id);
            } catch (\Throwable $e) {
                \Log::warning('tg delete failed', ['err' => $e->getMessage(), 'message_id' => $msg->id]);
            }
        }

        // 2) удалить связанные медиа (каждое отправлялось отдельным сообщением и хранит tg_message_id в files)
        foreach ($msg->files as $f) {
            if (!empty($f->tg_message_id)) {
                try {
                    $this->tgDeleteMessage($bot->bot_token, $chatId, (int)$f->tg_message_id);
                } catch (\Throwable $e) {
                    \Log::warning('tg delete file failed', [
                        'err'        => $e->getMessage(),
                        'message_id' => $msg->id,
                        'file_id'    => $f->id
                    ]);
                }
            }
        }

        // 3) мягкая пометка у оператора
        $msg->is_deleted = 1;
        $msg->save();

        return response()->json(['ok' => true]);
    }

    // ---------------- Telegram helpers ----------------

    // Возвращает message_id или null
    protected function tgSendMessage(string $token, int $chatId, string $text): ?int
    {
        $resp = Http::asForm()->post("https://api.telegram.org/bot{$token}/sendMessage", [
            'chat_id'    => $chatId,
            'text'       => $text,
            'parse_mode' => 'HTML',
        ]);

        if (!$resp->successful()) {
            $resp->throw();
        }

        return data_get($resp->json(), 'result.message_id');
    }

    // Возвращает message_id или null
    protected function tgSendFileByMime(string $token, int $chatId, string $publicPath, string $mime, ?string $caption = null): ?int
    {
        $abs = Storage::disk('public')->path($publicPath);
        $filename = basename($abs);

        $method = 'sendDocument';
        $field  = 'document';
        if (str_starts_with($mime, 'image/')) { $method = 'sendPhoto';  $field = 'photo';  }
        elseif (str_starts_with($mime, 'video/')) { $method = 'sendVideo';  $field = 'video';  }
        elseif (str_starts_with($mime, 'audio/')) { $method = 'sendAudio';  $field = 'audio';  }
        elseif (in_array($mime, ['audio/ogg', 'audio/opus'])) { $method = 'sendVoice';  $field = 'voice'; }

        $url = "https://api.telegram.org/bot{$token}/{$method}";

        $req = Http::asMultipart()->attach($field, fopen($abs, 'r'), $filename);

        $data = ['chat_id' => $chatId];
        if ($caption !== null && $caption !== '') {
            $data['caption']    = $caption;
            $data['parse_mode'] = 'HTML';
        }

        $resp = $req->post($url, $data);
        if (!$resp->successful()) {
            $resp->throw();
        }

        return data_get($resp->json(), 'result.message_id');
    }

    protected function tgEditText(string $token, int $chatId, int $messageId, string $text): void
    {
        $resp = Http::asForm()->post("https://api.telegram.org/bot{$token}/editMessageText", [
            'chat_id'    => $chatId,
            'message_id' => $messageId,
            'text'       => $text,
            'parse_mode' => 'HTML',
        ]);
        if (!$resp->successful()) {
            $resp->throw();
        }
    }

    protected function tgEditCaption(string $token, int $chatId, int $messageId, string $caption): void
    {
        $resp = Http::asForm()->post("https://api.telegram.org/bot{$token}/editMessageCaption", [
            'chat_id'    => $chatId,
            'message_id' => $messageId,
            'caption'    => $caption,
            'parse_mode' => 'HTML',
        ]);
        if (!$resp->successful()) {
            $resp->throw();
        }
    }

    protected function tgDeleteMessage(string $token, int $chatId, int $messageId): void
    {
        $resp = Http::asForm()->post("https://api.telegram.org/bot{$token}/deleteMessage", [
            'chat_id'    => $chatId,
            'message_id' => $messageId,
        ]);
        if (!$resp->successful()) {
            $resp->throw();
        }
    }
}
