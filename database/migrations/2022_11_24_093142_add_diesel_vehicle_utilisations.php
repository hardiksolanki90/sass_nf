<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDieselVehicleUtilisations extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('vehicle_utilisations', function (Blueprint $table) {
            $table->decimal('start_km')
                ->after('vehicle_capacity')
                ->default('0.00');

            $table->decimal('end_km')
                ->after('start_km')
                ->default('0.00');

            $table->decimal('diesel')
                ->after('end_km')
                ->default('0.00');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
