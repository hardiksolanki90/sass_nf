<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCustomerWarehouseMappingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('customer_warehouse_mappings', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid');
            $table->unsignedBigInteger('customer_id')->comment('comes from users');
            $table->unsignedBigInteger('customer_info_id')->comment('comes from cusotmer_infos')->nullable();
            $table->unsignedBigInteger('lob_id');
            $table->unsignedBigInteger('storage_location_id');
            $table->unsignedBigInteger('warehouse_id')->nullable();

            $table->foreign('customer_id')->references('id')->on('users');
            $table->foreign('lob_id')->references('id')->on('lobs');
            $table->foreign('storage_location_id')->references('id')->on('storagelocations');
            $table->foreign('warehouse_id')->references('id')->on('warehouses');

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
        Schema::dropIfExists('customer_warehouse_mappings');
    }
}
