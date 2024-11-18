<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddChannelIdToDistributionStocks extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('distribution_stocks', function (Blueprint $table) {
            $table->unsignedBigInteger('channel_id')
                ->after('salesman_id')
                ->nullable();
                
            $table->foreign('channel_id')
                ->references('id')
                ->on('channels');
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
            //
        });
    }
}
