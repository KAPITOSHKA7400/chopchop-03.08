<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\BotMsgTemplate;
use App\Models\BotMsgFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class BotMsgTemplateController extends Controller
{
    // Список шаблонов сообщений для конкретного бота
    public function index($bot_id)
    {
        $bot = \App\Models\Bot::findOrFail($bot_id);

        // Стартовое сообщение
        $startMsg = BotMsgTemplate::where('bot_id', $bot_id)
            ->where('type', 'start')->first();

        // Режим работы (work_time)
        $offlineMsg = BotMsgTemplate::where('bot_id', $bot_id)
            ->where('type', 'work_time')->first();

        // Кастомные сообщения
        $customMsgs = BotMsgTemplate::where('bot_id', $bot_id)
            ->where('type', 'custom')->get();

        return view('dashboard.msg-set', compact('bot', 'startMsg', 'offlineMsg', 'customMsgs'));
    }

    // Форма создания/редактирования
    public function edit(Request $request, $bot_id, $template_id = null)
    {
        $bot = \App\Models\Bot::findOrFail($bot_id);

        if ($template_id !== null && is_numeric($template_id)) {
            $template = BotMsgTemplate::with('files')->where('bot_id', $bot_id)->findOrFail($template_id);
        } else {
            $type = $request->query('type', null);
            $template = $type
                ? BotMsgTemplate::with('files')->where('bot_id', $bot_id)->where('type', $type)->first()
                : null;
        }

        if ($template) {
            $template->load('files');
        }

        return view('dashboard.partials.edit', compact('template', 'bot'));
    }

    // Сохранение (create) для всех типов
    public function store(Request $request, $bot_id, $template_id = null)
    {
        $request->validate([
            'msg_title'       => 'nullable|string|max:80',
            'msg_text'        => 'required|string|max:4000',
            'type'            => 'required|string',
            'attachments.*'   => 'file|max:5120',
        ]);

        DB::beginTransaction();
        try {
            $type     = $request->input('type', 'custom');
            $template = null;

            // Для start и work_time — апдейт, не создание
            if (in_array($type, ['start', 'work_time'])) {
                $template = BotMsgTemplate::where('bot_id', $bot_id)
                    ->where('type', $type)
                    ->first();
                if (!$template) {
                    $template = new BotMsgTemplate();
                    $template->bot_id = $bot_id;
                    $template->type   = $type;
                }
            } else {
                // custom: либо ищем по $template_id, либо создаём новое
                $template = $template_id
                    ? BotMsgTemplate::where('bot_id', $bot_id)->findOrFail($template_id)
                    : new BotMsgTemplate();
                $template->bot_id = $bot_id;
                $template->type   = $type;
            }

            // Заполняем поля
            $template->title     = $request->input('msg_title');
            $template->text      = $request->input('msg_text');
            $template->body      = $request->input('msg_text'); // сохраняем сразу в body
            $template->is_active = 1;
            $template->save();

            // Обработка файлов
            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    $file_type = $this->detectFileType($file);
                    $file_name = Str::random(16) . '_' . $file->getClientOriginalName();
                    $file_path = $file->storeAs("bot-msg-files/{$bot_id}", $file_name, 'public');

                    BotMsgFile::create([
                        'template_id' => $template->id,
                        'file_type'   => $file_type,
                        'file_name'   => $file->getClientOriginalName(),
                        'file_path'   => $file_path,
                        'file_mime'   => $file->getMimeType(),
                        'file_size'   => $file->getSize(),
                    ]);
                }
            }

            DB::commit();

            // ← вот здесь меняем редирект: отправляем на index этого бота
            return redirect()->route('msg-sets.index', $bot_id)
                ->with('success', 'Сообщение сохранено');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors('Ошибка: ' . $e->getMessage());
        }
    }

    // Помощник для определения типа файла
    protected function detectFileType($file)
    {
        $mime = $file->getMimeType();
        if (Str::startsWith($mime, 'image/')) return 'photo';
        if (Str::startsWith($mime, 'video/')) return 'video';
        if (Str::startsWith($mime, 'audio/')) return 'audio';
        return 'document';
    }

    // Форма создания
    public function create($bot_id)
    {
        $bot      = \App\Models\Bot::findOrFail($bot_id);
        $template = null;
        return view('dashboard.partials.edit', compact('bot', 'template'));
    }

    // Обычное обновление (update) для custom и остальных
    public function update(Request $request, $bot_id, $template_id)
    {
        // 0) Шаблон с файлами
        $template = BotMsgTemplate::with('files')->findOrFail($template_id);

        // 1) Удаляем отмеченные
        if ($request->filled('remove_files')) {
            $toDelete = BotMsgFile::whereIn('id', $request->input('remove_files'))->get();
            foreach ($toDelete as $file) {
                Storage::disk('public')->delete($file->file_path);
                $file->delete();
            }
        }

        // 2) Сколько осталось после удаления
        $existingCount = $template->files()->count();

        // 3) Сколько можно ещё добавить
        $maxNewFiles = max(0, 5 - $existingCount);

        // 4) Валидация на количество
        $request->validate([
            'attachments'   => ['nullable', 'array', "max:{$maxNewFiles}"],
            'attachments.*' => 'file|max:5120',
        ], [
            'attachments.max'   => "Вы можете добавить не более {$maxNewFiles} файлов (уже прикреплено {$existingCount}).",
            'attachments.*.max' => 'Каждый файл не должен превышать 5 МБ.',
        ]);

        // 5) Добавляем новые файлы
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $uploaded) {
                $path = $uploaded->store('bot-msg-files/' . $template->id, 'public');
                $template->files()->create([
                    'file_type' => $uploaded->getClientMimeType(),
                    'file_name' => $uploaded->getClientOriginalName(),
                    'file_path' => $path,
                    'file_mime' => $uploaded->getClientMimeType(),
                    'file_size' => $uploaded->getSize(),
                ]);
            }
        }

        // 6) Обновляем поля шаблона
        $template->update([
            'title' => $request->input('msg_title'),
            'body'  => $request->input('msg_text'), // ← теперь именно в body
        ]);

        // ← и здесь тоже: после update редирект на index бота
        return redirect()->route('msg-sets.index', $bot_id)
            ->with('success', 'Шаблон обновлён');
    }
    public function apiTemplates($bot_id)
    {
        // возвращаем только кастомные шаблоны, без стартового
        $templates = BotMsgTemplate::where('bot_id', $bot_id)
            ->where('type', 'custom')
            ->where('is_active', 1)
            ->get(['id', 'title', 'body']);  // body — там текст

        return response()->json($templates);
    }
}
