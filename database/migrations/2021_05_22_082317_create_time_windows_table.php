<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTimeWindowsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('time_windows', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('event_id');
            $table->boolean('is_available')->index()->default(1);
            $table->unsignedTinyInteger('week_day');
            $table->string('start_hour');
            $table->string('end_hour');
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
        Schema::dropIfExists('time_windows');
    }
}
