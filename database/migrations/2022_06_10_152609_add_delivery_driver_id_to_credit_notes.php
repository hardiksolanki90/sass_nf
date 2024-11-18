<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDeliveryDriverIdToCreditNotes extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('credit_notes', function (Blueprint $table) {
            $table->unsignedBigInteger('delivery_driver_id')
                ->after('salesman_id')
                ->nullable()
                ->comment('its a salesman_id but role is delivery driver');

            $table->unsignedBigInteger('van_id')
                ->after('trip_id')
                ->nullable()
                ->comment('its come form vans table');

            $table->foreign('delivery_driver_id')
                ->references('id')
                ->on('users');

            $table->foreign('van_id')
                ->references('id')
                ->on('vans');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('credit_notes', function (Blueprint $table) {
            //
        });
    }
}
