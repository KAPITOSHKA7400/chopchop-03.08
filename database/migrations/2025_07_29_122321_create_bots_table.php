<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bots', function (Blueprint $table) {
            $table->id(); // ID бота
            $table->unsignedBigInteger('user_id'); // ID пользователя (владелец)
            $table->string('bot_token')->unique(); // Токен бота
            $table->string('bot_name'); // Имя бота (то что вводит пользователь)
            $table->string('bot_username'); // Юзернейм из Телеграма
            $table->boolean('is_active')->default(true); // Активен ли бот
            $table->timestamps(); // created_at и updated_at

            // Внешний ключ (если есть таблица users)
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bots');
    }
};
