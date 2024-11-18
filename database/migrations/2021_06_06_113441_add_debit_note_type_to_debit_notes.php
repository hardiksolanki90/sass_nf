<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDebitNoteTypeToDebitNotes extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('debit_notes', function (Blueprint $table) {
            $table->enum('debit_note_type', ['debit_note', 'listing_fees', 'shelf_rent', 'rebate_discount'])
                ->after('supplier_recipt_number')
                ->default('debit_note');

            $table->enum('approval_status', ['Created', 'Updated', 'Deleted'])
                ->after('debit_note_type')
                ->default('Created');
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
