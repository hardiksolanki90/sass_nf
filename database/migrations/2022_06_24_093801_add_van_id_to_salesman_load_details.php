<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddVanIdToSalesmanLoadDetails extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('salesman_load_details', function (Blueprint $table) {
            $table->unsignedBigInteger('van_id')
                ->nullable()
                ->after('warehouse_id');

            $table->foreign('van_id')
                ->references('id')
                ->on('vans');
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
