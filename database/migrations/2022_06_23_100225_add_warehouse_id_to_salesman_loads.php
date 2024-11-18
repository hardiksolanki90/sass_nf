<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddWarehouseIdToSalesmanLoads extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('salesman_loads', function (Blueprint $table) {
            $table->unsignedBigInteger('storage_location_id')
                ->after('salesman_id')
                ->nullable();

            $table->unsignedBigInteger('warehouse_id')
                ->after('storage_location_id')
                ->nullable();
        });

        Schema::table('salesman_load_details', function (Blueprint $table) {
            $table->unsignedBigInteger('storage_location_id')
                ->after('salesman_id')
                ->nullable();

            $table->unsignedBigInteger('warehouse_id')
                ->after('storage_location_id')
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
        Schema::table('salesman_loads', function (Blueprint $table) {
            //
        });
    }
}
