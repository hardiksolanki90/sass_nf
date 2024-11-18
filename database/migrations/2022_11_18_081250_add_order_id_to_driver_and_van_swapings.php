<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddOrderIdToDriverAndVanSwapings extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('driver_and_van_swapings', function (Blueprint $table) {
            $table->unsignedBigInteger('order_id')
                ->after('organisation_id')
                ->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('driver_and_van_swapings', function (Blueprint $table) {
            //
        });
    }
}
