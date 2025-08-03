<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Bot;
use App\Models\User;

class BotInviteController extends Controller
{
    // Присоединение к боту по приглашению
    public function join(Request $request)
    {
        $request->validate([
            'invite_code' => 'required|string|size:12',
        ]);

        $bot = \App\Models\Bot::where('invite_code', $request->invite_code)->first();

        if (!$bot) {
            return back()->withErrors(['invite_code' => 'Неверный код приглашения']);
        }

        // Проверяем — не состоит ли уже пользователь в этом боте
        if ($bot->operators()->where('user_id', auth()->id())->exists()) {
            return back()->with('success', 'Вы уже добавлены как оператор!');
        }

        $bot->operators()->attach(auth()->id());

        return back()->with('success', 'Вы успешно присоединились к боту!');
    }


    // Генерация кода приглашения для бота (лучше через AJAX)
    public function generate(Request $request)
    {
        $request->validate([
            'bot_id' => 'required|exists:bots,id',
        ]);

        $bot = \App\Models\Bot::find($request->bot_id);

        // Только владелец бота может генерировать код
        if ($bot->owner_id !== auth()->id()) {   // ← Вот тут!
            return response()->json(['error' => 'Нет доступа'], 403);
        }

        $code = strtoupper(bin2hex(random_bytes(6))); // 12 символов
        $bot->invite_code = $code;
        $bot->save();

        return response()->json(['code' => $code]);
    }
}
