<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMobiatoReturnPickedToReturnViews extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('return_views', function (Blueprint $table) {
            $table->boolean('mobiato_return_picked')
                ->default(0)
                ->after('FLAG_NR');

            $table->unsignedBigInteger('salesman_unload_detail_id')
                ->nullable()
                ->after('mobiato_return_picked');

            $table->unsignedBigInteger('good_receipt_note_detail_detail_id')
                ->nullable()
                ->after('salesman_unload_detail_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('return_views', function (Blueprint $table) {
            //
        });
    }
}
