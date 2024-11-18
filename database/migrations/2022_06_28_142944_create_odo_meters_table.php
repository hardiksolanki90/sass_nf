<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOdoMetersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('odo_meters', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid');
            $table->unsignedBigInteger('organisation_id');
            $table->unsignedBigInteger('salesman_id')->nullable()->comment('comes from user table');
            $table->unsignedBigInteger('trip_id')->nullable();
            $table->unsignedBigInteger('van_id')->nullable();
            $table->unsignedBigInteger('start_fuel')->nullable();
            $table->unsignedBigInteger('end_fuel')->nullable();;
            $table->date('date');
            $table->enum('status',['start','end'])->default('start');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organisation_id')->references('id')->on('organisations')->onDelete('cascade');

            $table->foreign('salesman_id')->references('id')->on('users');
            $table->foreign('trip_id')->references('id')->on('trips');
            $table->foreign('van_id')->references('id')->on('vans');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('odo_meters');
    }
}
