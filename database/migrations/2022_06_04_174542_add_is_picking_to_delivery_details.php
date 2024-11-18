<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIsPickingToDeliveryDetails extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('delivery_details', function (Blueprint $table) {
            $table->unsignedBigInteger('salesman_id')->after('delivery_id')->nullable();

            $table->decimal('original_item_qty', 8,2)->after('open_qty')->default("0.00");

            $table->boolean('is_deleted')->after('delivery_status')->default(0);

            $table->boolean('is_picking')->after('is_deleted')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('delivery_details', function (Blueprint $table) {
            //
        });
    }
}
