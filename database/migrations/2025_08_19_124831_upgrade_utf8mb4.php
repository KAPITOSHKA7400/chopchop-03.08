<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Выполняем только для MySQL/MariaDB
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        // 1) Меняем кодировку БД (безопасно)
        DB::statement("ALTER DATABASE `".env('DB_DATABASE')."` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

        // 2) Таблицы, где есть текст/эмодзи — добавляй свои по мере необходимости
        $tables = [
            'chat_messages',
            'tg_chat_users',
            // 'bots',
            // 'bot_msg_templates',
            // 'chat_message_files',
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                DB::statement("ALTER TABLE `{$table}` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            }
        }
    }

    public function down(): void
    {
        // Откат не делаем. Если нужно — пропиши обратные ALTER'ы.
    }
};
