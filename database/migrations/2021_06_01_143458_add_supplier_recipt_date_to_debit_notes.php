<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSupplierReciptDateToDebitNotes extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('debit_notes', function (Blueprint $table) {
            $table->date('supplier_recipt_date')
                ->nullable()
                ->after('is_debit_note')
                ->comment('if is_debit_note = 0 then add data');

            $table->string('supplier_recipt_number')
                ->nullable()
                ->after('supplier_recipt_date')
                ->comment('if is_debit_note = 0 then add data');
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
