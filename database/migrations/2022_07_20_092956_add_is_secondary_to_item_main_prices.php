<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIsSecondaryToItemMainPrices extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('item_main_prices', function (Blueprint $table) {
            $table->boolean('is_secondary')
                ->after('item_shipping_uom')
                ->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('item_main_prices', function (Blueprint $table) {
            //
        });
    }
}
