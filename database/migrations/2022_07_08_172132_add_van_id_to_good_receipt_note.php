<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddVanIdToGoodReceiptNote extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('good_receipt_note', function (Blueprint $table) {
            $table->unsignedBigInteger('van_id')
                ->nullable()
                ->after('trip_id');

            $table->integer('source')->comment('GRN placed from. like 1:Mobile, 2:Backend, 3:Frontend')
                ->after('status')
                ->default('3');

            $table->foreign('van_id')->references('id')->on('vans');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('good_receipt_note', function (Blueprint $table) {
            //
        });
    }
}
