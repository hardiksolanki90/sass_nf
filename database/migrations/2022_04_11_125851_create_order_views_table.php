<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOrderViewsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('order_views')) {
            Schema::create('order_views', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid');
                $table->unsignedBigInteger('organisation_id');
                $table->unsignedBigInteger('order_id');
                $table->string('order_number');
                $table->string('customer_code')->nullable();
                $table->string('customer_name')->nullable();
                $table->string('merchandiser_code')->nullable();
                $table->string('merchandiser_name')->nullable();
                $table->string('item_name');
                $table->string('item_code');
                $table->string('item_uom');
                $table->string('item_qty');
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('order_views');
    }
}
