<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatMessage extends Model
{
    protected $table = 'chat_message'; // Убедитесь, что это именно ваша таблица
    public $timestamps = true;

    protected $fillable = [
        'bot_token',
        'chat_id',
        'telegram_user_id',
        'username',
        'text',
        'is_read',       // ← здесь
        'is_operator',
        'created_at',
        'updated_at',
    ];

    public function user()
    {
        return $this->belongsTo(TgChatUser::class, 'telegram_user_id', 'user_id');
    }
}
