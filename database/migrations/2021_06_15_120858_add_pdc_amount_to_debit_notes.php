<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPdcAmountToDebitNotes extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('debit_notes', function (Blueprint $table) {
            $table->decimal('pending_credit', 18, 3)
                ->default("0.00")
                ->after('grand_total');

            $table->decimal('pdc_amount', 18, 3)
                ->default("0.00")
                ->after('pending_credit');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('debit_notes', function (Blueprint $table) {
            //
        });
    }
}
