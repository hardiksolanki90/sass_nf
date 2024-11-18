<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOrderDeliveryLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('order_delivery_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('created_user');
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('delviery_id')->nullable();
            $table->unsignedBigInteger('updated_user')->nullable();
            $table->text('previous_request_body')->nullable();
            $table->text('request_body')->nullable();
            $table->string('action')->nullable()->comment('Order,Delivery,etc modules..');
            $table->string('status')->nullable()->comment('Created,Updated,etc..');
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
        Schema::dropIfExists('order_delivery_logs');
    }
}
