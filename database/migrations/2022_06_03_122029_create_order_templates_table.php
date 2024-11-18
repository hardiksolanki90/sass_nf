<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOrderTemplatesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('order_templates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->unsignedBigInteger('delivery_id')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable()->comment('Comes from user table');
            $table->unsignedBigInteger('item_id')->nullable()->comment('Comes from item table');

            $table->string('order_number', 50);
            $table->string('customer_code', 50);
            $table->string('customer_name');

            $table->date('lpo_raised_date');
            $table->date('lpo_request_date');

            $table->string('customer_lpo_nubmer');

            $table->string('item_code', 50);
            $table->string('item_name');

            $table->decimal('total_volume_in_case')->default('0.00')->comment('convert in CTN (ie. pcs to ctn)');
            $table->decimal('total_amount')->default('0.00');

            $table->integer('delivery_sequence');
            $table->integer('trip');
            $table->integer('trip_sequence');

            $table->string('vehicle', 50);
            $table->string('drive_name');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('order_templates');
    }
}
