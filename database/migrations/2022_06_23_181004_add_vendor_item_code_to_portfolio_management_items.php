<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddVendorItemCodeToPortfolioManagementItems extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('portfolio_management_items', function (Blueprint $table) {
            $table->string('vendor_item_code')
                ->nullable()
                ->after('store_price');
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
