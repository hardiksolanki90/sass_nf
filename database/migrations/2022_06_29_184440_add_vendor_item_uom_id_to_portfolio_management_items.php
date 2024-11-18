<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddVendorItemUomIdToPortfolioManagementItems extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('portfolio_management_items', function (Blueprint $table) {
            $table->unsignedBigInteger('vendor_item_uom_id')
                ->nullable()
                ->after('item_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('portfolio_management_items', function (Blueprint $table) {
            //
        });
    }
}
