@extends('layouts.app')

@section('content')
    <div class="flex flex-col items-center justify-center min-h-[70vh]">
        <div class="w-full max-w-md p-8 bg-white dark:bg-gray-800 rounded-lg shadow">
            <h2 class="text-2xl font-bold mb-6 text-center text-gray-900 dark:text-gray-100">Вход</h2>
            <!-- Здесь оставляй форму входа Jetstream/Fortify -->
            <form method="POST" action="{{ route('login') }}">
                @csrf

                <!-- Email -->
                <div class="mb-4">
                    <label for="email" class="block mb-1 text-gray-700 dark:text-gray-300">E-mail</label>
                    <input id="email" type="email" name="email" required autofocus class="w-full px-3 py-2 border rounded dark:bg-gray-700 dark:text-gray-200">
                    @error('email')
                    <span class="text-red-500 text-sm">{{ $message }}</span>
                    @enderror
                </div>

                <!-- Password -->
                <div class="mb-4">
                    <label for="password" class="block mb-1 text-gray-700 dark:text-gray-300">Пароль</label>
                    <input id="password" type="password" name="password" required class="w-full px-3 py-2 border rounded dark:bg-gray-700 dark:text-gray-200">
                    @error('password')
                    <span class="text-red-500 text-sm">{{ $message }}</span>
                    @enderror
                </div>

                <!-- Remember me -->
                <div class="flex items-center mb-6">
                    <input type="checkbox" name="remember" id="remember" class="mr-2">
                    <label for="remember" class="text-gray-700 dark:text-gray-300">Запомнить меня</label>
                </div>

                <button type="submit" class="w-full py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition">Войти</button>
            </form>
            <div class="mt-4 text-center">
                <a href="{{ route('password.request') }}" class="text-sm text-blue-600 hover:underline">Забыли пароль?</a>
            </div>
        </div>
    </div>
@endsection
