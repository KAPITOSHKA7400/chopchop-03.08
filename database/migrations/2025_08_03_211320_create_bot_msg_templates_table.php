<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('bot_msg_templates', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('bot_id');      // ID бота
            $table->string('type', 20);                // start, worktime, custom
            $table->string('title', 80);               // Название
            $table->text('body');                      // Текст сообщения
            $table->json('files')->nullable();         // Файлы (JSON)
            $table->timestamps();

            $table->foreign('bot_id')->references('id')->on('bots')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('bot_msg_templates');
    }
};
