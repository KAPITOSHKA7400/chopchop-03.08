<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('tg_chat_users', function (Blueprint $table) {
            $table->unsignedBigInteger('bot_id')->nullable()->after('id');
            // foreign key добавим потом!
        });
    }

    public function down()
    {
        Schema::table('tg_chat_users', function (Blueprint $table) {
            $table->dropColumn('bot_id');
        });
    }

};
