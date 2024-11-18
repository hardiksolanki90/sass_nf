<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddStorageLocationToInvoices extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->unsignedBigInteger('storage_location_id')
                ->after('route_id')
                ->nullable();

            $table->unsignedBigInteger('warehouse_id')
                ->after('storage_location_id')
                ->nullable();

            $table->foreign('storage_location_id')->references('id')
                ->on('storagelocations');

            $table->foreign('warehouse_id')->references('id')
                ->on('warehouses');
            
            $table->index('storage_location_id', 'warehouse_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('invoices', function (Blueprint $table) {
            //
        });
    }
}
