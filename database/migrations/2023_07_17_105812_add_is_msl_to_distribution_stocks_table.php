<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIsMslToDistributionStocksTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('distribution_stocks', function (Blueprint $table) {
            $table->tinyInteger('is_msl')->default(0)->after('is_out_of_stock');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('distribution_stocks', function (Blueprint $table) {
            $table->tinyInteger('is_msl');
        });
    }
}
