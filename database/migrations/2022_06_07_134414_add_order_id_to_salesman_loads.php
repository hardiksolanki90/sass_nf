<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddOrderIdToSalesmanLoads extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('salesman_loads', function (Blueprint $table) {
            $table->unsignedBigInteger('order_id')->after('route_id')->nullable();
            $table->unsignedBigInteger('delivery_id')->after('route_id')->nullable();

            $table->foreign('order_id')
                ->references('id')
                ->on('orders');

            $table->foreign('delivery_id')
                ->references('id')
                ->on('deliveries');
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
