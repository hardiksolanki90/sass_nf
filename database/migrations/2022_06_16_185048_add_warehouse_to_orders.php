<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddWarehouseToOrders extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('warehouse_id')
                ->nullable()
                ->after('storage_location_id');

            $table->foreign('warehouse_id')
                ->references('id')
                ->on('lobs');

            $table->unsignedBigInteger('lob_id')
                ->nullable()
                ->after('warehouse_id');

            $table->foreign('lob_id')
                ->references('id')
                ->on('lobs');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('orders', function (Blueprint $table) {
            //
        });
    }
}
