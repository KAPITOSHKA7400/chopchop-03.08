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
        Schema::table('chat_message', function (Illuminate\Database\Schema\Blueprint $table) {
            $table->tinyInteger('is_read')->default(0)->after('text')->comment('1 = Прочитано, 0 = Новое/Непрочитано');
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('chat_message', function (Illuminate\Database\Schema\Blueprint $table) {
            $table->dropColumn('is_read');
        });
    }

};
