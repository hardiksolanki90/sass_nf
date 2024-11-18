<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateItemBasePricesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('item_base_prices', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid');
            $table->unsignedBigInteger('storage_location_id')->index();
            $table->unsignedBigInteger('warehouse_id');
            $table->unsignedBigInteger('item_id')->index();
            $table->unsignedBigInteger('item_uom_id')->index();
            $table->decimal('price', 8, 2);
            $table->date('start_date');
            $table->date('end_date');
            $table->timestamps();

            $table->foreign('storage_location_id')->references('id')
                ->on('storagelocations');

            $table->foreign('warehouse_id')->references('id')
                ->on('warehouses');

            $table->foreign('item_id')->references('id')
                ->on('items');

            $table->foreign('item_uom_id')->references('id')
                ->on('item_uoms');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('item_base_prices');
    }
}
