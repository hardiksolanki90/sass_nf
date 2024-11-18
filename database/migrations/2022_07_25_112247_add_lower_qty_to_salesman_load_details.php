<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

use function GuzzleHttp\default_ca_bundle;

class AddLowerQtyToSalesmanLoadDetails extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('salesman_load_details', function (Blueprint $table) {
            $table->decimal('lower_qty', 8, 2)
                ->after('load_qty')
                ->default('0.00');
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
