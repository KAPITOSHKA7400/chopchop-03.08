@extends('layouts.app')

@section('content')
    <div class="flex flex-col items-center justify-center min-h-[70vh]">
        <div class="w-full max-w-md p-8 bg-white dark:bg-gray-800 rounded-lg shadow">
            <h2 class="text-2xl font-bold mb-6 text-center text-gray-900 dark:text-gray-100">Регистрация</h2>
            <form method="POST" action="{{ route('register') }}">
                @csrf

                <!-- Name -->
                <div class="mb-4">
                    <label for="name" class="block mb-1 text-gray-700 dark:text-gray-300">Имя</label>
                    <input id="name" type="text" name="name" value="{{ old('name') }}" required autofocus autocomplete="name"
                           class="w-full px-3 py-2 border rounded dark:bg-gray-700 dark:text-gray-200 @error('name') border-red-500 @enderror">
                    @error('name')
                    <span class="text-red-500 text-sm">{{ $message }}</span>
                    @enderror
                </div>

                <!-- Email Address -->
                <div class="mb-4">
                    <label for="email" class="block mb-1 text-gray-700 dark:text-gray-300">E-mail</label>
                    <input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="username"
                           class="w-full px-3 py-2 border rounded dark:bg-gray-700 dark:text-gray-200 @error('email') border-red-500 @enderror">
                    @error('email')
                    <span class="text-red-500 text-sm">{{ $message }}</span>
                    @enderror
                </div>

                <!-- Password -->
                <div class="mb-4">
                    <label for="password" class="block mb-1 text-gray-700 dark:text-gray-300">Пароль</label>
                    <input id="password" type="password" name="password" required autocomplete="new-password"
                           class="w-full px-3 py-2 border rounded dark:bg-gray-700 dark:text-gray-200 @error('password') border-red-500 @enderror">
                    @error('password')
                    <span class="text-red-500 text-sm">{{ $message }}</span>
                    @enderror
                </div>

                <!-- Confirm Password -->
                <div class="mb-6">
                    <label for="password_confirmation" class="block mb-1 text-gray-700 dark:text-gray-300">Подтвердите пароль</label>
                    <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password"
                           class="w-full px-3 py-2 border rounded dark:bg-gray-700 dark:text-gray-200">
                </div>

                <button type="submit" class="w-full py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition">Зарегистрироваться</button>
            </form>
            <div class="mt-4 text-center">
                <a href="{{ route('login') }}" class="text-sm text-blue-600 hover:underline">Уже есть аккаунт?</a>
            </div>
        </div>
    </div>
@endsection
