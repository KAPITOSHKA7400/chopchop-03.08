@extends('layouts.app')

@section('content')
    <div class="container mx-auto mt-8">
        @include('partials.dashboard-menu')

        {{-- Форма добавления бота --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 mb-8">
            <form action="#" method="POST" class="flex flex-col md:flex-row gap-4 items-end">
                @csrf
                <div class="w-full md:w-1/4">
                    <label for="bot_name" class="block text-gray-700 dark:text-gray-200 mb-1">Имя бота</label>
                    <input type="text" name="bot_name" id="bot_name" required
                           class="w-full px-4 py-2 rounded border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:outline-none focus:border-blue-400">
                </div>
                <div class="flex-1">
                    <label for="bot_token" class="block text-gray-700 dark:text-gray-200 mb-1">Токен бота</label>
                    <input type="text" name="bot_token" id="bot_token" required
                           class="w-full px-4 py-2 rounded border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:outline-none focus:border-blue-400">
                </div>
                <button type="submit"
                        class="px-6 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition">Добавить бота</button>
            </form>
        </div>

        @if(isset($bots) && count($bots) == 0)
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 mb-8">
                @if ($errors->any())
                    <div class="mb-4 text-red-600">
                        {{ $errors->first() }}
                    </div>
                @endif

                @if (session('success'))
                    <div class="mb-4 text-green-600">
                        {{ session('success') }}
                    </div>
                @endif

                <form action="{{ route('bots.invite.join') }}" method="POST" class="flex gap-4 items-end">
                    @csrf
                    <div>
                        <label for="invite_code" class="block text-gray-700 dark:text-gray-200 mb-1">Код приглашения</label>
                        <input type="text" name="invite_code" id="invite_code" required
                               class="px-4 py-2 rounded border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:outline-none focus:border-blue-400">
                    </div>
                    <button type="submit"
                            class="px-6 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition">Присоединиться к боту</button>
                </form>
            </div>
        @endif

        {{-- Список ботов --}}
        @if(isset($bots) && count($bots))
            <div class="space-y-4">
                @foreach($bots as $bot)
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow flex flex-col md:flex-row items-center md:items-stretch p-4 border border-gray-200 dark:border-gray-700">
                        <div class="flex items-center flex-1 min-w-0">
                            <span class="inline-flex items-center mr-3">
                                <span class="w-4 h-4 rounded-full mr-2 border-2 border-white dark:border-gray-800
                                    {{ $bot->is_active ? 'bg-green-500' : 'bg-red-500' }}">
                                </span>
                            </span>
                            <div class="flex flex-col">
                                <div class="">
                                    <span class="truncate text-lg font-medium text-gray-900 dark:text-gray-100 mr-2">{{ $bot->bot_name }}</span>
                                    <span class="truncate text-gray-500 dark:text-gray-400 text-base">({{ $bot->bot_username }})</span>
                                </div>
                                <span class="text-xs text-gray-400">(Админ бота - {{ $bot->owner->name ?? 'неизвестно' }})</span>
                            </div>
                        </div>

                        @php
                            $isOwner = ($bot->owner_id ?? $bot->user_id) == auth()->id();
                        @endphp

                        <div class="flex flex-wrap gap-2 mt-4 md:mt-0 md:ml-6">
                            @if($isOwner)
                                <a href="#" class="px-4 py-2 border rounded hover:bg-gray-100 dark:hover:bg-gray-700 transition">Наборы сообщений</a>
                                <a href="#" class="px-4 py-2 border rounded hover:bg-gray-100 dark:hover:bg-gray-700 transition">Редактировать</a>
                                <a href="#" class="px-4 py-2 border rounded hover:bg-gray-100 dark:hover:bg-gray-700 transition">Статистика</a>
                                <button type="button" onclick="generateAndCopyInvite({{ $bot->id }}, this)"
                                        class="px-4 py-2 border rounded hover:bg-gray-100 dark:hover:bg-gray-700 transition">
                                    Скопировать приглашение
                                </button>
                                <a href="#" class="px-4 py-2 border rounded hover:bg-gray-100 dark:hover:bg-gray-700 transition">Скопировать запрос для группы</a>
                                <form action="{{ route('bot.destroy', $bot->id) }}" method="POST" class="inline" onsubmit="return confirm('Удалить бота &laquo;{{ $bot->bot_name }}&raquo;?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                            class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700 transition">
                                        Удалить
                                    </button>
                                </form>
                            @else
                                <a href="{{ url('/dashboard/chats?bot='.$bot->id) }}"
                                   class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition">
                                    Открыть чаты
                                </a>
                            @endif
                        </div>

                    </div>
                @endforeach
            </div>
        @else
            <div class="text-center text-gray-500 dark:text-gray-400 py-12">
                У вас пока нет добавленных ботов.<br>
                Используйте форму выше, чтобы добавить первого бота!
            </div>
        @endif
    </div>

    <!-- Модалка для копирования кода -->
    <div id="inviteModal"
         style="display:none"
         class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-50">
        <div class="bg-white dark:bg-gray-900 rounded-lg shadow-lg p-8 w-full max-w-xs text-center">
            <h2 class="text-lg font-bold mb-3 text-gray-900 dark:text-gray-100">Код приглашения</h2>
            <input
                id="inviteCodeInput"
                type="text"
                class="w-full px-3 py-2 border rounded bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-gray-100 mb-4 text-center font-mono"
                readonly
                onclick="this.select()"
            >
            <div class="flex justify-center gap-2">
                <button
                    class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700"
                    onclick="copyInviteCode()">
                    Копировать
                </button>
                <button
                    class="px-4 py-2 bg-gray-400 text-white rounded hover:bg-gray-500"
                    onclick="closeInviteModal()">
                    Закрыть
                </button>
            </div>
            <div id="inviteCopyStatus" class="mt-2 text-green-600 text-sm" style="display:none;">Скопировано!</div>
        </div>
    </div>

    @push('scripts')
        <script>
            function generateAndCopyInvite(botId, btn) {
                fetch('/bots/invite/generate', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ bot_id: botId })
                })
                    .then(response => {
                        if (!response.ok) {
                            return response.json().then(err => { throw err; });
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data.code) {
                            showInviteModal(data.code);
                        } else {
                            alert(data.error || 'Ошибка генерации кода');
                        }
                    })
                    .catch(err => {
                        console.log('Ошибка:', err);
                    });
            }

            function showInviteModal(code) {
                const modal = document.getElementById('inviteModal');
                const input = document.getElementById('inviteCodeInput');
                const status = document.getElementById('inviteCopyStatus');
                input.value = code;
                modal.style.display = 'flex';
                status.style.display = 'none';
            }

            function closeInviteModal() {
                document.getElementById('inviteModal').style.display = 'none';
            }

            function copyInviteCode() {
                const input = document.getElementById('inviteCodeInput');
                const status = document.getElementById('inviteCopyStatus');
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(input.value)
                        .then(() => {
                            status.style.display = 'block';
                            setTimeout(() => { status.style.display = 'none'; }, 1200);
                        });
                } else {
                    input.select();
                    document.execCommand('copy');
                    status.style.display = 'block';
                    setTimeout(() => { status.style.display = 'none'; }, 1200);
                }
            }
        </script>
    @endpush
@endsection
