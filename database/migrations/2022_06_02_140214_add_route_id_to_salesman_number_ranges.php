<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddRouteIdToSalesmanNumberRanges extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('salesman_number_ranges', function (Blueprint $table) {
            $table->unsignedBigInteger('route_id')->nullable()->after('salesman_id');

            $table->string('exchange_from', 20)->nullable()->after('unload_to');
            $table->string('exchange_to', 20)->nullable()->after('exchange_from');

            $table->foreign('route_id')->references('id')->on('routes')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('salesman_number_ranges', function (Blueprint $table) {
            //
        });
    }
}
