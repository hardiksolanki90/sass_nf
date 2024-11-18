<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateVehicleUtilisationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('vehicle_utilisations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('region_id')->nullable();
            $table->string('region_code', 50)->nullable();
            $table->string('region_name', 191)->nullable();
            $table->unsignedBigInteger('vehicle_id')->nullable();
            $table->string('vehicle_code', 50)->nullable();
            $table->decimal('invoice_count', 18, 2)->comment('Windows Delivered')->default(0)->nullable();
            $table->decimal('invoice_qty', 18, 2)->comment('Delivered qty')->default(0)->nullable();
            $table->decimal('customer_count', 18, 2)->comment('Windoes to Deliver')->default(0)->nullable();
            $table->decimal('delivery_qty', 18, 2)->default(0)->nullable();
            $table->decimal('cancle_count', 18, 2)->default(0)->nullable();
            $table->decimal('cancel_qty', 18, 2)->default(0)->nullable();
            $table->date('transcation_date')->comment('Date')->nullable();
            $table->decimal('less_delivery_count', 18, 2)->comment('Windows <=10 case')->default(0)->nullable();
            $table->decimal('order_count', 18, 2)->comment('Number of Orders')->default(0)->nullable();
            $table->decimal('order_qty', 18, 2)->default(0)->comment('Oreder Qty')->nullable();
            $table->decimal('vehicle_capacity', 18, 2)->default(0)->nullable();
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
        Schema::dropIfExists('vehicle_utilisations');
    }
}
