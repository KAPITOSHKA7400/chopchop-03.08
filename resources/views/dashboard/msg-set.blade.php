@extends('layouts.app')

@section('content')
    <div class="container mx-auto mt-8">
        @include('partials.dashboard-menu')

        <h2 class="text-xl font-bold mb-6">
            Наборы сообщений для бота: <span class="text-blue-600">{{ $bot->bot_name }}</span>
        </h2>

        {{-- 1. Стартовое сообщение --}}
        <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow mb-6">
            <div class="flex items-center justify-between">
                <div>
                    <div class="font-semibold text-gray-700 dark:text-gray-200 mb-1">Стартовое сообщение</div>
                    <div class="text-gray-600 dark:text-gray-400 mb-2 text-sm">
                        Данное сообщение отправляется, когда пользователь пишет <span class="font-mono bg-gray-100 dark:bg-gray-900 px-2 py-1 rounded">/start</span>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-900 px-4 py-3 rounded border border-gray-200 dark:border-gray-700 text-gray-900 dark:text-gray-100 font-mono">
                        {{ $startMsg->body ?? 'Стартовое сообщение не задано.' }}
                    </div>
                </div>
                <a href="{{ route('msg-sets.edit', ['bot' => $bot->id]) }}?type=start"
                   class="ml-4 px-5 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition">
                    Редактировать
                </a>
            </div>
        </div>

        {{-- 2. Режим работы --}}
        <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow mb-6">
            <div class="flex items-center justify-between">
                <div>
                    <div class="font-semibold text-gray-700 dark:text-gray-200 mb-1">Режим работы</div>
                    <div class="text-gray-600 dark:text-gray-400 mb-2 text-sm">
                        Автоматически отправляется, если нет операторов в сети
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-900 px-4 py-3 rounded border border-gray-200 dark:border-gray-700 text-gray-900 dark:text-gray-100 font-mono">
                        {{ $offlineMsg->body ?? 'Сообщение о режиме работы не задано.' }}
                    </div>
                </div>
                <a href="{{ route('msg-sets.edit', ['bot' => $bot->id]) }}?type=work_time"
                   class="ml-4 px-5 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition">
                    Редактировать
                </a>
            </div>
        </div>

        {{-- 3. Кастомные сообщения --}}
        <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow">
            <div class="flex items-center justify-between mb-4">
                <div class="font-semibold text-gray-700 dark:text-gray-200">Кастомные сообщения</div>
                <a href="{{ route('msg-sets.create', $bot->id) }}"
                   class="ml-4 px-5 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition">
                    Добавить
                </a>
            </div>
            @if(isset($customMsgs) && count($customMsgs))
                <div class="space-y-3">
                    @foreach($customMsgs as $msg)
                        <div class="flex items-center justify-between">
                            <div class="bg-gray-50 dark:bg-gray-900 px-4 py-3 rounded border border-gray-200 dark:border-gray-700 text-gray-900 dark:text-gray-100 font-mono w-full">
                                {{ $msg->body }}
                            </div>
                            <a href="{{ route('msg-sets.edit', ['bot' => $bot->id, 'template_id' => $msg->id]) }}"
                               class="ml-4 px-5 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition">
                                Редактировать
                            </a>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="text-gray-400 dark:text-gray-500">
                    Нет кастомных сообщений. Используйте кнопку "Добавить".
                </div>
            @endif
        </div>
    </div>
@endsection
@push('scripts')
    <script>
        // Передаём Vue-приложению список кастомных шаблонов (исключая start и work_time)
        window.customTemplates = {!!
            $customMsgs
                ->filter(function($m) {
                    return ! in_array($m->type, ['start', 'work_time']);
                })
                ->map(function($m) {
                    return [
                        'id'    => $m->id,
                        'title' => $m->title,
                        'body'  => $m->body,
                    ];
                })
                ->values()
                ->toJson()
        !!};
    </script>
@endpush
