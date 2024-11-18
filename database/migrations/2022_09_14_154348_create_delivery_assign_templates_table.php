<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDeliveryAssignTemplatesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('delivery_assign_templates', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid');
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('delivery_id');
            $table->unsignedBigInteger('delivery_details_id');
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('delivery_driver_id');
            $table->unsignedBigInteger('van_id');
            $table->unsignedBigInteger('storage_location_id')->nullable();
            $table->unsignedBigInteger('warehouse_id')->nullable();
            $table->unsignedBigInteger('item_id');
            $table->unsignedBigInteger('item_uom_id')->nullable();
            $table->decimal('qty', 8, 2)->default('0.00');
            $table->decimal('amount', 8, 2)->default('0.00');
            $table->integer('delivery_sequence')->nullable();
            $table->integer('trip')->nullable();
            $table->integer('trip_sequence')->nullable();
            $table->boolean('is_last_trip')->default(0);
            $table->boolean('is_deleted')->default(0);
            $table->unsignedBigInteger('reason_id')->nullable();
            $table->timestamps();

            $table->foreign('order_id')->references('id')
                ->on('orders');

            $table->foreign('delivery_id')->references('id')
                ->on('deliveries');

            $table->foreign('customer_id')->references('id')
                ->on('users');

            $table->foreign('delivery_driver_id')->references('id')
                ->on('users');

            $table->foreign('van_id')->references('id')
                ->on('vans');

            $table->foreign('item_id')->references('id')
                ->on('items');

            $table->foreign('item_uom_id')->references('id')
                ->on('item_uoms');

            $table->foreign('storage_location_id')->references('id')
                ->on('storagelocations');

            $table->foreign('warehouse_id')->references('id')
                ->on('warehouses');

            $table->foreign('reason_id')->references('id')
                ->on('reason_types');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('delivery_assign_templates');
    }
}
