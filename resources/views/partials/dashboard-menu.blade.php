{{-- resources/views/partials/dashboard-menu.blade.php --}}
<div class="flex gap-4 mb-8 ps-6 pe-6">
    <a href="{{ route('dashboard') }}"
       class="px-4 py-2 rounded {{ request()->is('dashboard') ? 'bg-blue-600 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-900 dark:text-gray-100' }}">
        Боты
    </a>
    <a href="{{ route('dashboard.chats') }}"
       class="px-4 py-2 rounded {{ request()->is('dashboard/chats') ? 'bg-blue-600 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-900 dark:text-gray-100' }}">
        Чаты
    </a>

    {{-- Настройки с выпадающим меню --}}
    <div class="relative" x-data="{ open: false }" @mouseleave="open = false">
        <button @mouseenter="open = true" @click="open = !open"
                class="px-4 py-2 rounded bg-gray-200 dark:bg-gray-700 text-gray-900 dark:text-gray-100 flex items-center gap-1 focus:outline-none">
            Настройки
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2"
                 viewBox="0 0 24 24">
                <path d="M19 9l-7 7-7-7"/>
            </svg>
        </button>
        <div x-show="open"
            x-transition
            class="absolute left-0 mt-2 w-48 bg-white dark:bg-gray-800 border dark:border-gray-700 rounded shadow-lg z-10"
            @mouseenter="open = true"
            @mouseleave="open = false"
            style="display: none">
            <a href="{{ url('/profile') }}"
               class="block px-4 py-2 text-gray-800 dark:text-gray-100 hover:bg-gray-100 dark:hover:bg-gray-700">Профиль</a>
            <a href="{{ url('/statistics') }}"
               class="block px-4 py-2 text-gray-800 dark:text-gray-100 hover:bg-gray-100 dark:hover:bg-gray-700">Статистика</a>
            <a href="{{ url('/security') }}"
               class="block px-4 py-2 text-gray-800 dark:text-gray-100 hover:bg-gray-100 dark:hover:bg-gray-700">Безопасность</a>
            <a href="{{ url('/faq') }}"
               class="block px-4 py-2 text-gray-800 dark:text-gray-100 hover:bg-gray-100 dark:hover:bg-gray-700">FAQ</a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit"
                        class="w-full text-left px-4 py-2 text-red-600 hover:bg-gray-100 dark:hover:bg-gray-700">
                    Выйти
                </button>
            </form>
        </div>
    </div>

    {{-- Смена статуса --}}
    <div class="relative" x-data="{ open: false }" @mouseleave="open = false">
        <button @mouseenter="open = true" @click="open = !open"
                class="px-4 py-2 rounded bg-gray-200 dark:bg-gray-700 text-gray-900 dark:text-gray-100 flex items-center gap-1 focus:outline-none">
            В сети
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2"
                 viewBox="0 0 24 24">
                <path d="M19 9l-7 7-7-7"/>
            </svg>
        </button>
        <div x-show="open"
             x-transition
             class="absolute left-0 mt-2 w-48 bg-white dark:bg-gray-800 border dark:border-gray-700 rounded shadow-lg z-10"
             @mouseenter="open = true"
             @mouseleave="open = false"
             style="display: none">
            <a href="{{ url('/profile') }}"
               class="block px-4 py-2 text-gray-800 dark:text-gray-100 hover:bg-gray-100 dark:hover:bg-gray-700">В сети</a>
            <a href="{{ url('/statistics') }}"
               class="block px-4 py-2 text-gray-800 dark:text-gray-100 hover:bg-gray-100 dark:hover:bg-gray-700">АФК (отошёл)</a>
            <form method="POST">
                @csrf
                <button type="submit"
                        class="w-full text-left px-4 py-2 text-red-600 hover:bg-gray-100 dark:hover:bg-gray-700">
                    Не в сети
                </button>
            </form>
        </div>
    </div>
</div>
