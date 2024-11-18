<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMobiatoOrderPickedToRfGenViews extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('rf_gen_views', function (Blueprint $table) {
            $table->boolean('mobiato_order_picked')
                ->default(0)
                ->after('OrderPicked');

            $table->unsignedBigInteger('order_detail_id')
                ->nullable()
                ->after('mobiato_order_picked');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('rf_gen_views', function (Blueprint $table) {
            //
        });
    }
}
