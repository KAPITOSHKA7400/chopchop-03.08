<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_message', function (Blueprint $table) {
            $table->increments('id');
            $table->string('bot_token', 255);
            $table->unsignedBigInteger('chat_id');
            $table->unsignedBigInteger('telegram_user_id');
            $table->string('username', 255)->nullable();
            $table->text('text')->nullable();
            $table->dateTime('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_message');
    }
};
