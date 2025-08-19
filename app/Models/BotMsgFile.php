<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class BotMsgFile extends Model
{
    protected $table = 'bot_msg_files';

    protected $fillable = [
        'template_id',
        'file_type',   // photo, video, audio, document и т.д.
        'file_name',
        'file_path',
        'file_mime',
        'file_size',
    ];

    public function template()
    {
        return $this->belongsTo(BotMsgTemplate::class, 'template_id');
    }
    public function edit($botId, $templateId)
    {
        $template = BotMsgTemplate::findOrFail($templateId);
        $files = DB::table('bot_msg_files')
            ->where('template_id', $template->id)
            ->get();

        return view('dashboard.partials.edit', compact('template', 'files'));
    }
}
