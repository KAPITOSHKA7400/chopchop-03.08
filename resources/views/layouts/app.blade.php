<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Laravel') }}</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
{{--    <script src="{{ asset('js/chat.js') }}"></script>--}}


</head>
<body class="bg-gray-100 dark:bg-gray-900 text-gray-900 dark:text-gray-100 min-h-screen flex flex-col transition-colors">
{{-- Header --}}
<header class="w-full bg-white dark:bg-gray-800 shadow py-4 px-8 flex justify-between items-center">
    <div class="text-xl font-bold"><a href="/">{{ config('app.name', 'ChopChop') }}</a></div>
    <nav class="flex items-center gap-4">
        @auth
            <a href="/dashboard" class="hover:underline">Дашборд</a>
        @endauth
        @guest
            <a href="/login" class="hover:underline">Войти</a>
            <a href="/register" class="hover:underline">Регистрация</a>
        @endguest

        <button id="dark-toggle"
                class="ml-4 px-3 py-1 border rounded text-sm text-gray-600 dark:text-gray-200 border-gray-400 dark:border-gray-600">
            🌙
        </button>
    </nav>
</header>

{{-- Content --}}
<main class="flex-grow">
    @yield('content')
</main>

{{-- Footer --}}
<footer class="w-full bg-white dark:bg-gray-800 text-center py-4 text-gray-500 dark:text-gray-400 shadow">
    &copy; {{ date('Y') }} {{ config('app.name', 'ChopChop') }}
</footer>

<script>
    // Переключатель дарк-мода
    const btn = document.getElementById('dark-toggle');
    btn.addEventListener('click', () => {
        document.documentElement.classList.toggle('dark');
        if(document.documentElement.classList.contains('dark')) {
            localStorage.setItem('theme', 'dark');
        } else {
            localStorage.setItem('theme', 'light');
        }
    });

    // Применить тему при загрузке
    if(localStorage.getItem('theme') === 'dark') {
        document.documentElement.classList.add('dark');
    }
</script>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
@stack('scripts')
</body>
</html>
