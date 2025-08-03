@extends('layouts.app')

@section('content')
    <div class="container mx-auto mt-8">
        @include('partials.dashboard-menu')
        <div class="max-h-screen h-full p-6 bg-white dark:bg-gray-900 rounded shadow">

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

            {{-- СПИСОК ЧАТОВ трогать нельзя --}}
            <div class="flex h-[600px] bg-white dark:bg-gray-800 rounded-[10px] overflow-hidden">
                {{-- Sidebar (список чатов) --}}
                <aside class="relative w-1/3 border-r dark:border-gray-700 overflow-y-auto pr-3">
                    <div class="p-3 border-b border-gray-200 dark:border-gray-700">
                        <h5 class="leading-6 font-medium text-gray-900 dark:text-white">
                            Диалоги с пользователями Telegram
                        </h5>
                        <p class="mt-1 max-w-2xl text-xs text-gray-500 dark:text-gray-400">
                            Список пользователей, которые общались с ботом
                        </p>
                    </div>
                    <div class="flex flex-col" id="chat-list-panel">
                        @if(empty($chatUsers) || $chatUsers->isEmpty())
                            <div class="px-4 py-5 sm:px-6 text-center text-gray-500 dark:text-gray-400">
                                Нет активных диалогов
                            </div>
                        @else
                            <ul role="list" class="divide-y divide-gray-200 dark:divide-gray-700 max-h-[650px] pr-1 custom-scrollbar">
                                @foreach ($chatUsers as $user)
                                    <li>
                                        <a href="#" data-userid="{{ $user->user_id }}"
                                                class="chat-user-link block hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors duration-150 ease-in-out
                                                {{ (request('user_id') == $user->user_id) ? 'bg-gray-700 active' : '' }}"
                                                onclick="setActiveUser({{ $user->user_id }})">
                                            <div class="flex items-center p-2">
                                                <div class="min-w-0 flex-1 flex items-center">
                                                    {{-- Аватар пользователя --}}
                                                    <div class="flex-shrink-0">
                                                        @if(!empty($user->avatar_url))
                                                            <img src="{{ $user->avatar_url }}"
                                                                 alt="{{ $user->first_name ?? $user->username }}"
                                                                 class="h-16 w-16 rounded-[10px] object-cover" />
                                                        @else
                                                            <div class="h-16 w-16 rounded-[10px] bg-indigo-100 dark:bg-indigo-900 flex items-center justify-center text-indigo-800 dark:text-indigo-200 font-bold">
                                                                {{ strtoupper(substr($user->first_name ?? $user->username ?? 'U', 0, 1)) }}
                                                            </div>
                                                        @endif
                                                    </div>

                                                    <div class="min-w-0 md:grid md:grid-cols-[1fr,auto] flex-1 px-4 ">
                                                        <div class="w-max hidden md:block">
                                                            <div>
                                                                <p class="text-sm font-medium text-indigo-600 dark:text-indigo-400 truncate">
                                                                    {{ $user->first_name }} {{ $user->last_name }}
                                                                </p>
                                                            </div>
                                                            <div>
                                                                {{-- Отображаем последнее сообщение --}}
                                                                @php
                                                                    $latestMessage = ($user->messages && $user->messages instanceof \Illuminate\Support\Collection)
                                                                        ? $user->messages->first()
                                                                        : null;
                                                                @endphp

                                                                @if ($latestMessage)
                                                                    <p class="flex items-center text-sm text-gray-500 dark:text-gray-400">
                                                                        <svg class="flex-shrink-0 mr-1.5 h-5 w-5 text-gray-400 dark:text-gray-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd" />
                                                                        </svg>
                                                                        {{ $latestMessage->created_at->format('d.m.Y H:i') }}
                                                                    </p>
                                                                    <p class="text-sm text-gray-900 dark:text-gray-200 truncate">
                                                                        {{ Str::limit($latestMessage->text, 50) }}
                                                                    </p>
                                                                @else
                                                                    <p class="text-sm text-gray-900 dark:text-gray-200 truncate">
                                                                        Начало диалога
                                                                    </p>
                                                                    <p class="mt-2 flex items-center text-sm text-gray-500 dark:text-gray-400">
                                                                        <svg class="flex-shrink-0 mr-1.5 h-5 w-5 text-gray-400 dark:text-gray-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd" />
                                                                        </svg>
                                                                        Нет сообщений
                                                                    </p>
                                                                @endif
                                                            </div>
                                                        </div>
                                                        <div class="w-[30px] right-0">
                                                            @php
                                                                $hasUnread = $user->messages->contains(function($msg) {
                                                                    return isset($msg->is_read) && !$msg->is_read;
                                                                });
                                                            @endphp
                                                            @if($hasUnread)
                                                                <span title="Новое сообщение">
                                                                    <svg class="h-5 w-5 text-red-500 animate-bounce" fill="currentColor" viewBox="0 0 20 20">
                                                                        <circle cx="10" cy="10" r="6"/>
                                                                    </svg>
                                                                </span>
                                                            @endif
                                                        </div>
                                                    </div>
                                                </div>
                                                <div>
                                                    <svg class="h-5 w-5 text-gray-400 dark:text-gray-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                        <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                                                    </svg>
                                                </div>
                                            </div>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </aside>

                <aside class="relative w-full h-full px-4 py-5 text-center text-gray-500 dark:text-gray-400">
                    <div id="chat-app"
                         data-initial-user-id="{{ $openedChat ? $openedChat->id : '' }}">
                        <chat-component :active-user-id="activeUserId"></chat-component>
                    </div>
                </aside>

            </div>
        </div>
    </div>
    <script>
        window.activeUserId = document.getElementById('chat-app').dataset.initialUserId || null;

        function setActiveUser(id) {
            console.log('[DEBUG] setActiveUser вызван для', id);
            window.activeUserId = id;
            // Обновляем проп Vue
            if (window.app && window.app._instance) {
                window.app._instance.props.activeUserId = id;
            }

            // 1) выделяем активную строку
            document.querySelectorAll('.chat-user-link').forEach(link => {
                link.classList.remove('bg-gray-700','active');
                if (Number(link.dataset.userid) === Number(id)) {
                    link.classList.add('bg-gray-700','active');
                }
            });

            // 2) POST-запрос в контроллер markAsRead
            fetch(`/dashboard/chats/read/${id}`, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
            })
                .then(res => res.json())
                .then(json => {
                    if (json.ok) {
                        // убираем бейдж в строке чата
                        const link = document.querySelector(`.chat-user-link[data-userid="${id}"]`);
                        link?.querySelector('svg.text-red-500')?.remove();
                    }
                });
        }

        document.addEventListener('DOMContentLoaded', function () {
            if (window.activeUserId) setActiveUser(window.activeUserId);
        });
    </script>
@endsection
