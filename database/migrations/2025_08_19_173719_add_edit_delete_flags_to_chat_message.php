<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('chat_message', function (Blueprint $table) {
            // ID сообщения в Telegram для текстовых сообщений оператора
            $table->unsignedBigInteger('tg_message_id')->nullable()->after('chat_id');

            // Служебные флаги для интерфейса оператора
            $table->boolean('is_deleted')->default(false)->after('is_read');
            $table->boolean('is_edited')->default(false)->after('is_deleted');
        });
    }

    public function down(): void {
        Schema::table('chat_message', function (Blueprint $table) {
            $table->dropColumn(['tg_message_id','is_deleted','is_edited']);
        });
    }
};
