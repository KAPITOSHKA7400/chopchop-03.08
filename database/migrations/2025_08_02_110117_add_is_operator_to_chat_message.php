<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIsOperatorToChatMessage extends Migration
{
    public function up()
    {
        Schema::table('chat_message', function (Blueprint $table) {
            $table->boolean('is_operator')->default(false)->after('text');
        });
    }

    public function down()
    {
        Schema::table('chat_message', function (Blueprint $table) {
            $table->dropColumn('is_operator');
        });
    }
}
