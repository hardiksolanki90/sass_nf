<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddStorageLocationIdToSalesmanUnloads extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('salesman_unloads', function (Blueprint $table) {
            $table->unsignedBigInteger('storage_location_id')
                ->after('organisation_id')
                ->nullable();

            $table->unsignedBigInteger('warehouse_id')
                ->after('storage_location_id')
                ->nullable();

            $table->unsignedBigInteger('van_id')
                ->after('warehouse_id')
                ->nullable();

            $table->foreign('storage_location_id')->references('id')->on('storagelocations');
            $table->foreign('warehouse_id')->references('id')->on('warehouses');
            $table->foreign('van_id')->references('id')->on('vans');
        });

        Schema::table('salesman_unload_details', function (Blueprint $table) {
            $table->unsignedBigInteger('route_id')
                ->after('salesman_unload_id')
                ->nullable();

            $table->unsignedBigInteger('storage_location_id')
                ->after('route_id')
                ->nullable();

            $table->unsignedBigInteger('warehouse_id')
                ->after('storage_location_id')
                ->nullable();

            $table->unsignedBigInteger('van_id')
                ->after('warehouse_id')
                ->nullable();

            $table->foreign('route_id')->references('id')->on('routes');
            $table->foreign('storage_location_id')->references('id')->on('storagelocations');
            $table->foreign('warehouse_id')->references('id')->on('warehouses');
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
        Schema::table('salesman_unloads', function (Blueprint $table) {
            //
        });
    }
}
