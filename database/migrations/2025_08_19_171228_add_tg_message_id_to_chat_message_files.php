<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('chat_message_files', function (Blueprint $table) {
            // ID сообщения в Telegram для каждого отправленного файла
            $table->unsignedBigInteger('tg_message_id')->nullable()->after('size');
        });
    }

    public function down(): void {
        Schema::table('chat_message_files', function (Blueprint $table) {
            $table->dropColumn('tg_message_id');
        });
    }
};
