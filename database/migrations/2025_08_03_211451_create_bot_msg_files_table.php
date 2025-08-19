<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('bot_msg_files', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('template_id');
            $table->string('file_type', 20);    // photo, video, audio, document
            $table->string('file_name');
            $table->string('file_path');
            $table->string('file_mime', 100)->nullable();
            $table->integer('file_size')->nullable();
            $table->timestamps();

            $table->foreign('template_id')->references('id')->on('bot_msg_templates')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('bot_msg_files');
    }
};
