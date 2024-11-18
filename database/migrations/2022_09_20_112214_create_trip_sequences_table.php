<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTripSequencesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('trip_sequences', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid');
            $table->unsignedBigInteger('salesman_id')->index()->comment('come from users table');
            $table->unsignedBigInteger('route_id')->nullable();
            $table->date('date')->index();
            $table->time('login_time');
            $table->time('logout_time')->nullable();
            $table->smallInteger('trip_number')->default(1);

            $table->foreign('salesman_id')->references('id')
            ->on('users');

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
        Schema::dropIfExists('trip_sequences');
    }
}
