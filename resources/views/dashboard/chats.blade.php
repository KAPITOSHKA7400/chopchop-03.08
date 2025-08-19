@extends('layouts.app')

@section('content')
    <div class="container mx-auto mt-8">
        @include('partials.dashboard-menu')
        <div class="max-h-screen h-full p-3 bg-white dark:bg-gray-900 rounded shadow">

            {{-- Фильтр трогать нельзя --}}
            <form method="GET" class="flex gap-2 mb-6 flex-wrap">
                {{-- Боты --}}
                <div>
                    <label class="block text-xs text-gray-500">Боты</label>
                    <select name="bot_id" class="w-40 px-3 py-2 border rounded bg-white dark:bg-gray-800">
                        <option value="">Все</option>
                        @foreach($bots as $bot)
                            <option value="{{ $bot->id }}" {{ request('bot_id') == $bot->id ? 'selected' : '' }}>
                                {{ $bot->bot_name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Статус важности --}}
                <div>
                    <label class="block text-xs text-gray-500">Статус важности</label>
                    <select name="priority" class="w-40 px-3 py-2 border rounded bg-white dark:bg-gray-800">
                        <option value="">Все</option>
                        <option value="important" {{ request('priority') == 'important' ? 'selected' : '' }}>Важные</option>
                        <option value="not_important" {{ request('priority') == 'not_important' ? 'selected' : '' }}>Неважные</option>
                    </select>
                </div>

                {{-- Статус решения --}}
                <div>
                    <label class="block text-xs text-gray-500">Статус решения</label>
                    <select name="is_solved" class="w-40 px-3 py-2 border rounded bg-white dark:bg-gray-800">
                        <option value="">Все</option>
                        <option value="1" {{ request('is_solved') === '1' ? 'selected' : '' }}>Решённые</option>
                        <option value="0" {{ request('is_solved') === '0' ? 'selected' : '' }}>Нерешённые</option>
                    </select>
                </div>

                {{-- Ответственный --}}
                <div>
                    <label class="block text-xs text-gray-500">Ответственный</label>
                    <select name="operator_id" class="w-40 px-3 py-2 border rounded bg-white dark:bg-gray-800">
                        <option value="">Все</option>
                        @foreach($operators as $operator)
                            <option value="{{ $operator->id }}" {{ request('operator_id') == $operator->id ? 'selected' : '' }}>
                                {{ $operator->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Поиск (Ник или ID) --}}
                <div>
                    <label class="block text-xs text-gray-500">Поиск (Telegram)</label>
                    <input name="search" type="text" placeholder="Ник или Telegram ID"
                           value="{{ request('search') }}"
                           class="w-48 px-3 py-2 border rounded bg-white dark:bg-gray-800 truncate">
                </div>

                {{-- На странице --}}
                <div>
                    <label class="block text-xs text-gray-500">На странице</label>
                    <select name="per_page" class="w-24 px-3 py-2 border rounded bg-white dark:bg-gray-800">
                        <option value="50" {{ request('per_page') == 50 ? 'selected' : '' }}>50</option>
                        <option value="100" {{ request('per_page') == 100 ? 'selected' : '' }}>100</option>
                        <option value="200" {{ request('per_page') == 200 ? 'selected' : '' }}>200</option>
                    </select>
                </div>

                {{-- Кнопка --}}
                <div class="flex items-end">
                    <button type="submit"
                            class="px-6 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition">
                        Обновить
                    </button>
                </div>
            </form>

            {{-- Прокинуть данные из blade в js --}}
            <script>
                window.chatUsers = @json($chatUsers);
            </script>

            {{-- ЧАТ полностью на Vue --}}
            <div id="vue-chat-app" class="flex w-full h-[600px]"></div>
        </div>
    </div>
@endsection
