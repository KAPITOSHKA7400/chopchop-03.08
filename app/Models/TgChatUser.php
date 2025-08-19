<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TgChatUser extends Model
{
    protected $table = 'tg_chat_users';
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'username',
        'first_name',
        'last_name',
        'avatar_url',
        'created_at',
        'updated_at',
    ];

    // Добавляем отношение к сообщениям
    public function messages()
    {
        return $this->hasMany(ChatMessage::class, 'telegram_user_id', 'user_id')
            ->orderBy('created_at', 'desc');
    }
}
