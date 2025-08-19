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
        Schema::table('bot_msg_templates', function (Blueprint $table) {
            $table->unsignedInteger('sort')->default(0)->after('type');
        });
    }

    public function down()
    {
        Schema::table('bot_msg_templates', function (Blueprint $table) {
            $table->dropColumn('sort');
        });
    }

};
