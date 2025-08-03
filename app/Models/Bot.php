<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Bot extends Model
{
    use HasFactory;

    // Явно указываем таблицу (можно не указывать, если имя bots)
    protected $table = 'bots';

    protected $fillable = [
        'user_id',
        'bot_token',
        'bot_name',
        'bot_username',
        'is_active',
        'owner_id',
        'invite_code',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Владелец бота
    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    // Автор (создатель бота)
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // Операторы (связанные пользователи через bot_user)
    public function operators()
    {
        return $this->belongsToMany(User::class, 'bot_user', 'bot_id', 'user_id');
    }
}
