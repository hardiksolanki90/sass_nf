<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddZoneIdToCustomerRegions extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('customer_regions', function (Blueprint $table) {
            $table->unsignedBigInteger('zone_id')
                ->after('customer_id')
                ->index();

            // $table->foreign('zone_id')->references('id')->on('zones');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('customer_regions', function (Blueprint $table) {
            $table->dropColumn('zone_id');
        });
    }
}
