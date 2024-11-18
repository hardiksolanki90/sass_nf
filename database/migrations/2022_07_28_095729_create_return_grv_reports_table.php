<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateReturnGrvReportsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('return_grv_reports', function (Blueprint $table) {
            $table->id();

            $table->uuid('uuid');
            $table->unsignedBigInteger('organisation_id');

            $table->date('date');
            $table->unsignedBigInteger('region_id')->nullable();
            $table->string('region_code', 50)->nullable();
            $table->string('region_name', 191)->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->string('customer_code', 50)->nullable();
            $table->string('customer_name', 191)->nullable();
            $table->integer('qty')->nullable();
            $table->unsignedBigInteger('reason_id')->nullable();
            $table->string('reason_name', 191)->nullable();
            $table->unsignedBigInteger('salesman_id');
            $table->string('salesman_code', 50)->nullable();
            $table->string('salesman_name', 191)->nullable();
            $table->integer('amount')->nullable();

            $table->foreign('region_id')->references('id')->on('regions');
            $table->foreign('customer_id')->references('id')->on('users');
            $table->foreign('reason_id')->references('id')->on('reason_types');
            $table->foreign('salesman_id')->references('id')->on('users');


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
        Schema::dropIfExists('return_grv_reports');
    }
}
