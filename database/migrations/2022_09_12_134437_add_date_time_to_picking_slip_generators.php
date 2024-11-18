<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDateTimeToPickingSlipGenerators extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('picking_slip_generators', function (Blueprint $table) {
            $table->time('time')
                ->after('date')
                ->default(date('H:i:s'));
            $table->time('date_time')
                ->after('time')
                ->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('picking_slip_generators', function (Blueprint $table) {
            //
        });
    }
}
