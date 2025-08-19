<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ChatMessageFile extends Model
{
    protected $table = 'chat_message_files';

    protected $fillable = [
        'chat_message_id',
        'file_name',
        'file_path',
        'mime_type',
        'size',
    ];

    // добавляем в JSON
    protected $appends = ['url'];

    public function message()
    {
        return $this->belongsTo(ChatMessage::class, 'chat_message_id');
    }

    public function getUrlAttribute()
    {
        $path = $this->file_path;
        if (!$path) return null;
        if (Str::startsWith($path, ['http://', 'https://'])) return $path;
        // FILESYSTEM_DISK=local + APP_URL=... => Storage::url даст /storage/...
        return url(Storage::url($path));
    }
}
