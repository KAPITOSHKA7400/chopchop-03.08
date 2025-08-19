<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\BotMsgTemplate;
use Illuminate\Support\Facades\Storage;

class TemplateApiController extends Controller
{
    /**
     * Возвращает тело шаблона и все прикреплённые к нему файлы.
     *
     * GET /api/templates/{id}
     */
    public function show($id)
    {
        $tpl = BotMsgTemplate::with('files')
            ->findOrFail($id);

        // Преобразуем коллекцию файлов в удобный JSON:
        $files = $tpl->files->map(fn($f) => [
            'id'   => $f->id,
            'url'  => Storage::url($f->file_path),
            'mime' => $f->file_mime,
        ]);

        return response()->json([
            'body'  => $tpl->body,
            'files' => $files,
        ]);
    }
}
