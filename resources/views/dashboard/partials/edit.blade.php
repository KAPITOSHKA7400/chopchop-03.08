@extends('layouts.app')

@section('content')
    <div class="container mx-auto mt-8 max-w-5xl">
        <h2 class="text-xl font-bold mb-6 text-gray-900 dark:text-gray-100">
            Редактирование сообщения
        </h2>

        <form
            action="{{ isset($template)
        // вместо 'msg' — 'template'
        ? route('msg-sets.update', ['bot' => $bot->id, 'template' => $template->id])
        : route('msg-sets.store',   ['bot' => $bot->id]) }}"
            method="POST"
            enctype="multipart/form-data"
            class="space-y-6"
        >
            @csrf

            {{--При редактировании подменяем метод на PUT--}}
            @if(isset($template))
                @method('PUT')
            @endif

            {{--Тип сообщения (start, work_time, custom)--}}
            <input
                type="hidden"
                name="type"
                value="{{ old('type', $template->type ?? request('type', 'custom')) }}"
            >

            {{--Название--}}
            <div>
                <label for="msg_title" class="block text-gray-700 dark:text-gray-200 mb-1 font-semibold">
                    Название
                </label>
                <input
                    type="text"
                    id="msg_title"
                    name="msg_title"
                    maxlength="80"
                    value="{{ old('msg_title', $template->title ?? '') }}"
                    class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600
                           bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100
                           focus:outline-none focus:border-blue-400 transition"
                    placeholder="Введите название сообщения"
                >
                <div class="text-xs text-gray-400 mt-1">Не длиннее 80 символов.</div>
            </div>

            {{--Текст--}}
            <div>
                <label for="msg_text" class="block text-gray-700 dark:text-gray-200 mb-1 font-semibold">
                    Текст сообщения
                </label>
                <textarea
                    id="msg_text"
                    name="msg_text"
                    maxlength="4000"
                    rows="7"
                    class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600
                           bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100
                           focus:outline-none focus:border-blue-400 resize-y transition"
                    placeholder="Введите текст сообщения"
                >{{ old('msg_text', $template->body ?? '') }}</textarea>
                <div class="text-xs text-gray-400 mt-1">Максимум 4000 символов.</div>
            </div>

            <div class="flex justify-between">
                {{-- 1) Ошибка над инпутом --}}
                @error('attachments')
                <div class="text-red-600 text-sm mb-1">
                    {{ $message }}
                </div>
                @enderror

                {{--Файлы--}}
                <div>
                    {{-- 2) Форма загрузки новых файлов --}}
                    <div>
                        <label class="block text-gray-700 dark:text-gray-200 mb-1 font-semibold">
                            Файлы для сообщения
                        </label>
                        <input
                            type="file"
                            name="attachments[]"
                            multiple
                            accept="*"
                            onchange="handleFiles(this)"
                            class="block w-full text-gray-900 dark:text-gray-200
                                border border-gray-300 dark:border-gray-600
                                rounded-lg bg-white dark:bg-gray-800
                                file:mr-4 file:py-2 file:px-4 file:rounded file:border-0
                                file:text-sm file:bg-blue-50 file:text-blue-700
                                hover:file:bg-blue-100 transition">
                        <div class="text-xs text-gray-400 mt-1">
                            Максимум 5 файлов, до 5 МБ каждый.
                        </div>
                        <div id="selected-files" class="mt-2 text-xs text-gray-500 dark:text-gray-400"></div>
                    </div>

                    @php
                        // если шаблон существует, считаем уже загруженные файлы
                        $existingCount = isset($template) ? $template->files->count() : 0;
                    @endphp

                    {{--Список уже загруженных--}}
                    {{-- 3) Список уже загруженных файлов --}}
                    @if(isset($template) && $template->files->isNotEmpty())
                        <div class="mt-4 space-y-2">
                            <strong class="block text-gray-700 dark:text-gray-200 mb-1">
                                Загруженные файлы:
                            </strong>
                            <ul class="list-disc list-inside text-gray-700 dark:text-gray-300">
                                @foreach($template->files as $file)
                                    <li class="flex items-center space-x-3">
                                        {{-- Превью --}}
                                        @php
                                            $mime = $file->file_mime;
                                            $url  = asset('storage/'.$file->file_path);
                                        @endphp

                                        @if(str_starts_with($mime, 'image/'))
                                            <img src="{{ $url }}"
                                                 alt="{{ $file->file_name }}"
                                                 class="w-16 h-16 object-cover rounded border">
                                        @elseif(str_starts_with($mime, 'video/'))
                                            <video controls class="w-16 h-16 rounded border">
                                                <source src="{{ $url }}" type="{{ $mime }}">
                                            </video>
                                        @elseif(str_starts_with($mime, 'audio/'))
                                            <audio controls class="w-full max-w-xs h-8">
                                                <source src="{{ $url }}" type="{{ $mime }}">
                                            </audio>
                                        @else
                                            <a href="{{ $url }}" target="_blank" class="text-blue-600 hover:underline">
                                                {{ $file->file_name }}
                                            </a>
                                        @endif

                                        {{-- Чекбокс «Удалить» --}}
                                        <label class="inline-flex items-center space-x-1">
                                            <input type="checkbox"
                                                   name="remove_files[]"
                                                   value="{{ $file->id }}"
                                                   class="form-checkbox h-4 w-4 text-red-600">
                                            <span class="text-sm text-red-600">Удалить</span>
                                        </label>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div id="selected-files" class="mt-2 text-xs text-gray-500 dark:text-gray-400"></div>
                </div>

                {{--Кнопки--}}
                <div class="flex gap-3 justify-end">
                    <button
                        type="submit"
                        class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition h-fit">
                        Сохранить
                    </button>
                    <a
                        href="{{ url()->previous() }}"
                        class="px-6 py-2 bg-gray-400 text-white rounded-lg hover:bg-gray-500 transition h-fit">
                        Отменить
                    </a>
                </div>
            </div>
        </form>
    </div>

    @php
        // если шаблон существует, считаем уже загруженные файлы
        $existingCount = isset($template) ? $template->files->count() : 0;
    @endphp

    @push('scripts')
        <script>
            const maxFiles            = 5;
            const existingFilesCount  = {{ $existingCount }};

            function handleFiles(input) {
                const files = Array.from(input.files);
                let html = '', error = '';

                // проверяем общее количество
                if (existingFilesCount + files.length > maxFiles) {
                    error = `Максимум ${maxFiles} файлов на сообщение (уже ${existingFilesCount}).`;
                } else {
                    for (const f of files) {
                        const size = f.size / 1024 / 1024;
                        if (size > 5) {
                            error = `Каждый файл не должен превышать 5 МБ.`;
                            break;
                        }
                        html += `<div>${f.name} (${size.toFixed(2)} МБ)</div>`;
                    }
                }

                const container = document.getElementById('selected-files');
                if (error) {
                    container.innerHTML = `<span class="text-red-600">${error}</span>`;
                    input.value = '';     // сбрасываем выбор
                } else {
                    container.innerHTML = html;
                }
            }
        </script>
    @endpush
@endsection
