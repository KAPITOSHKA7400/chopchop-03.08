<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use App\Models\BotMsgFile;


class BotMsgTemplate extends Model
{
    protected $table = 'bot_msg_templates';

    protected $fillable = [
        'bot_id',
        'type',      // 'start', 'work_time', 'custom'
        'title',     // короткое название (может быть nullable для стартового/режима)
        'text',      // текст сообщения
        'body',
        'sort',      // для сортировки custom-сообщений (если хочешь)
        'is_active', // можно включать/выключать (1/0)
    ];

    public function files()
    {
        return $this->hasMany(BotMsgFile::class, 'template_id');
    }

    public function getFilesAttribute()
    {
        return collect(
            DB::table('bot_msg_files')
                ->where('template_id', $this->id)
                ->get()
        );
    }


    // Если хочешь добавить связь с ботом (если есть таблица ботов)
    // public function bot()
    // {
    //     return $this->belongsTo(Bot::class, 'bot_id');
    // }
}
