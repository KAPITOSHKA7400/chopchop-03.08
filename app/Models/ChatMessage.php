<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\ChatMessageFile;

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
        'is_read',
        'is_operator',
        'tg_message_id',
        'is_deleted',
        'is_edited',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'is_operator' => 'boolean',
        'is_auto'     => 'boolean',
        'is_read'     => 'boolean',
        'is_deleted'  => 'boolean', // <—
        'is_edited'   => 'boolean', // <—
    ];

    public function user()
    {
        return $this->belongsTo(TgChatUser::class, 'telegram_user_id', 'user_id');
    }

    public function files()
    {
        return $this->hasMany(\App\Models\ChatMessageFile::class, 'chat_message_id');
    }
}
