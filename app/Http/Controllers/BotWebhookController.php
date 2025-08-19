<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Telegram\Bot\Api;
use App\Models\Bot;
use App\Models\BotMsgTemplate;

class BotWebhookController extends Controller
{
    /**
     * Метод-обработчик вебхука Telegram.
     * Вызывается при любом апдейте от бота.
     */
    public function __invoke(Request $request, $botId)
    {
        // 1) Находим бота по ID (в таблице bots у вас должен быть токен)
        $bot = Bot::findOrFail($botId);

        // 2) Инициализируем SDK на его токене
        $telegram = new Api($bot->token);

        // 3) Получаем объект апдейта
        $update = $telegram->getWebhookUpdate();

        // 4) Извлекаем chat_id
        $chatId = data_get($update, 'message.chat.id')
            ?: data_get($update, 'callback_query.message.chat.id');

        // 5) Смотрим, пришла ли команда /start
        $text = data_get($update, 'message.text', '');
        if (trim($text) === '/start') {
            // 6) Берём из БД шаблон типа "start"
            $template = BotMsgTemplate::with('files')
                ->where('bot_id', $bot->id)
                ->where('type', 'start')
                ->where('is_active', 1)
                ->first();

            if ($template) {
                // 7) Сначала отправляем вложения
                foreach ($template->files as $file) {
                    $path = storage_path('app/public/' . $file->file_path);

                    if (str_starts_with($file->file_mime, 'image/')) {
                        $telegram->sendPhoto([
                            'chat_id' => $chatId,
                            'photo'   => fopen($path, 'r'),
                        ]);
                    } elseif (str_starts_with($file->file_mime, 'video/')) {
                        $telegram->sendVideo([
                            'chat_id' => $chatId,
                            'video'   => fopen($path, 'r'),
                        ]);
                    } elseif (str_starts_with($file->file_mime, 'audio/')) {
                        $telegram->sendAudio([
                            'chat_id' => $chatId,
                            'audio'   => fopen($path, 'r'),
                        ]);
                    } else {
                        // документ или любой другой файл
                        $telegram->sendDocument([
                            'chat_id'  => $chatId,
                            'document'=> fopen($path, 'r'),
                        ]);
                    }
                }

                // 8) Затем отправляем текст сообщения
                $telegram->sendMessage([
                    'chat_id'    => $chatId,
                    'text'       => $template->text,
                    'parse_mode' => 'HTML',
                ]);
            }
        }

        // 9) Обязательно возвращаем 200, чтобы Telegram считал, что всё успешно
        return response('OK', 200);
    }
}
