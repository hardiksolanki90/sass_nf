<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDriverAndVanSwapingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('driver_and_van_swapings', function (Blueprint $table) {
            $table->id();

            $table->uuid('uuid');
            $table->unsignedBigInteger('organisation_id');
            $table->unsignedBigInteger('new_salesman_id')->nullable();
            $table->unsignedBigInteger('old_salesman_id')->nullable();
            $table->unsignedBigInteger('old_van_id')->nullable();
            $table->unsignedBigInteger('new_van_id')->nullable();
            $table->unsignedBigInteger('login_user_id')->comment('Which user change this record for log.');
            $table->unsignedBigInteger('reason_id')->nullable();
            $table->date('date');
            $table->timestamps();

            $table->softDeletes();

            $table->foreign('organisation_id')->references('id')->on('organisations')->onDelete('cascade');
            $table->foreign('new_salesman_id')->references('id')->on('users');
            $table->foreign('old_salesman_id')->references('id')->on('users');
            $table->foreign('old_van_id')->references('id')->on('vans');
            $table->foreign('new_van_id')->references('id')->on('vans');
            $table->foreign('reason_id')->references('id')->on('reason_types');
            $table->foreign('login_user_id')->references('id')->on('users');
            // $table->foreign('delivery_id')->references('id')->on('deliveries');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('driver_and_van_swapings');
    }
}
