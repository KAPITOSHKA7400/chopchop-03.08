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
            $table->unsignedBigInteger('owner_id')->nullable();
        });
    }
    public function down()
    {
        Schema::table('bots', function (Blueprint $table) {
            $table->dropColumn('owner_id');
        });
    }

};
