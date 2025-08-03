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
            $table->unsignedBigInteger('bot_id')->nullable(false)->change();
            $table->foreign('bot_id')->references('id')->on('bots')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::table('tg_chat_users', function (Blueprint $table) {
            $table->dropForeign(['bot_id']);
            $table->unsignedBigInteger('bot_id')->nullable()->change();
        });
    }

};
