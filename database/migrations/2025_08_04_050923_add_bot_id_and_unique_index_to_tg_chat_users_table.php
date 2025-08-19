<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tg_chat_users', function (Blueprint $table) {
            // 1) Создаём новый уникальный индекс по (bot_id, user_id)
            $table->unique(['bot_id', 'user_id'], 'tg_chat_users_bot_user_unique');

            // 2) Добавляем внешний ключ на таблицу bots
            $table->foreign('bot_id', 'tg_chat_users_bot_fk')
                ->references('id')
                ->on('bots')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('tg_chat_users', function (Blueprint $table) {
            // Откат: убираем foreign key и уникальный индекс
            $table->dropForeign('tg_chat_users_bot_fk');
            $table->dropUnique('tg_chat_users_bot_user_unique');
        });
    }
};
