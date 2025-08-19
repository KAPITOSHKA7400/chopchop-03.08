<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Bot;
use App\Models\BotMsgTemplate;


class MsgSetController extends Controller
{
    public function index($botId)
    {
        $bot = Bot::findOrFail($botId);
        // Позже здесь будет выборка наборов сообщений для этого бота
        return view('dashboard.msg-set', compact('bot'));
    }

    public function edit($bot, $msg = null)
    {
        $bot = Bot::findOrFail($bot);

        if ($msg) {
            $message = BotMsgTemplate::findOrFail($msg);
            // Можно добавить проверку на принадлежность к боту
        } else {
            $message = null;
        }

        return view('dashboard.partials.edit', compact('bot', 'message'));
    }

    public function update(Request $request, $bot, $msg = null)
    {
        $request->validate([
            'msg_title' => 'required|string|max:80',
            'msg_text'  => 'required|string|max:4000',
            'attachments.*' => 'nullable|file|max:5120', // 5 MB
        ]);

        if ($msg) {
            $message = BotMsgTemplate::findOrFail($msg);
            $message->update([
                'title' => $request->msg_title,
                'text'  => $request->msg_text,
                // файлы отдельно
            ]);
        } else {
            $message = BotMsgTemplate::create([
                'bot_id' => $bot,
                'title'  => $request->msg_title,
                'text'   => $request->msg_text,
                // файлы отдельно
            ]);
        }

        // Пример загрузки файлов:
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $file->store('attachments'); // можно записать в отдельную таблицу
            }
        }

        return redirect()->route('msg-set.index', $bot)->with('success', 'Сообщение сохранено!');
    }

}
