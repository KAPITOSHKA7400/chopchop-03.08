<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BotMsgTemplate;
use Illuminate\Http\Request;

class BotMsgTemplateApiController extends Controller
{
    public function index($botId)
    {
        // Вернём только кастомные сообщения (type = 'custom' и is_active)
        $templates = BotMsgTemplate::where('bot_id', $botId)
            ->where('type', 'custom')
            ->where('is_active', 1)
            ->get(['id', 'title', 'text', 'body', 'type']);

        return response()->json($templates);
    }
}
