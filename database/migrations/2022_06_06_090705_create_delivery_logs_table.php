<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDeliveryLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('delivery_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid');

            $table->unsignedBigInteger('organisation_id');
            $table->unsignedBigInteger('changed_user_id')->nullable();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->unsignedBigInteger('order_detail_id')->nullable();
            $table->unsignedBigInteger('delivery_id')->nullable();
            $table->unsignedBigInteger('delivery_detail_id')->nullable();
            $table->unsignedBigInteger('item_id')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('salesman_id')->comment('come from delivery_details')->nullable();
            $table->unsignedBigInteger('item_uom_id')->nullable();
            $table->unsignedBigInteger('reason_id')->nullable();

            $table->string('customer_code')->nullable()->index();
            $table->string('customer_name')->nullable()->index();
            $table->string('salesman_code')->nullable()->index();
            $table->string('salesman_name')->nullable()->index();

            $table->string('item_name')->nullable()->index();
            $table->string('item_code')->nullable()->index();
            $table->string('item_uom')->nullable()->index();
            $table->decimal('item_qty', 8, 3)->default('0.00');
            $table->decimal('original_item_qty', 8, 3)->default('0.00');
            $table->enum('action', ['deleted', 'change qty'])->default('change qty')->index();
            $table->string('reason')->nullable()->index();

            $table->foreign('organisation_id')
                ->references('id')
                ->on('organisations')
                ->onDelete('cascade');

            $table->foreign('changed_user_id')
                ->references('id')
                ->on('users');

            $table->foreign('customer_id')
                ->references('id')
                ->on('users');

            $table->foreign('salesman_id')
                ->references('id')
                ->on('users');

            $table->foreign('order_id')
                ->references('id')
                ->on('orders');

            $table->foreign('order_detail_id')
                ->references('id')
                ->on('order_details');

            $table->foreign('delivery_id')
                ->references('id')
                ->on('deliveries');

            $table->foreign('delivery_detail_id')
                ->references('id')
                ->on('delivery_details');

            $table->foreign('item_id')
                ->references('id')
                ->on('items');

            $table->foreign('item_uom_id')
                ->references('id')
                ->on('item_uoms');

            $table->foreign('reason_id')
                ->references('id')
                ->on('reason_types');


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
        Schema::dropIfExists('delivery_logs');
    }
}
