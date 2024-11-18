<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddRequestedItemUomIdToSalesmanLoadDetails extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('salesman_load_details', function (Blueprint $table) {
            $table->decimal('requested_qty', 18, 2)
                ->after('lower_qty')
                ->default('0.00');
            $table->unsignedBigInteger('requested_item_uom_id')
                ->after('requested_qty')
                ->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('salesman_load_details', function (Blueprint $table) {
            //
        });
    }
}
