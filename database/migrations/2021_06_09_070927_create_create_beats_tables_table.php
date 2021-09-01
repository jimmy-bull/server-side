<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class CreateCreateBeatsTablesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('create_beats_tables', function (Blueprint $table) {
            $table->increments('id');
            $table->string('src');
            $table->string('image_link');
            $table->string('instagram_name');
            $table->string('downloadable');
            $table->string('title');
            $table->integer('price');
            $table->string('beat_link');
            $table->integer('tags');
            $table->integer('bpm');
            $table->string('mood');
            $table->string('key');
            $table->string('duration');
            $table->string('genre');
            $table->string('sub_title');
            $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('create_beats_tables');
    }
}
