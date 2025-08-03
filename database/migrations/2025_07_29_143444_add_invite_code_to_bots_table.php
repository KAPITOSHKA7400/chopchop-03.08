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
        Schema::table('bots', function (Blueprint $table) {
            $table->string('invite_code', 12)->nullable()->unique();
        });
    }
    public function down()
    {
        Schema::table('bots', function (Blueprint $table) {
            $table->dropColumn('invite_code');
        });
    }

};
