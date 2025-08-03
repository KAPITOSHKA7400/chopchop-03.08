<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tg_chat_users', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedBigInteger('user_id')->comment('Telegram User ID / Telegram-ID пользователя');
            $table->string('username', 255)->nullable()->comment('Telegram Username / Никнейм');
            $table->string('first_name', 255)->nullable()->comment('First name / Имя');
            $table->string('last_name', 255)->nullable()->comment('Last name / Фамилия');
            $table->string('avatar_url', 500)->nullable()->comment('Temporary URL to avatar from Telegram / Ссылка на аватарку');
            $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('Created at / Дата и время создания записи');
            $table->dateTime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('Updated at / Дата и время обновления записи');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tg_chat_users');
    }
};

