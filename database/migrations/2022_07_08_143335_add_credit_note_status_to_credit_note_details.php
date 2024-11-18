<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCreditNoteStatusToCreditNoteDetails extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('credit_note_details', function (Blueprint $table) {
            $table->enum('credit_note_status', [
                'Partial-Returned',
                'Returned'
            ])->nullable()
                ->after('invoice_total');

            $table->unsignedBigInteger('credit_note_notes_id')
                ->after('credit_note_status')
                ->nullable();

            $table->enum('return_status', ['full', 'partial'])
                ->after('credit_note_notes_id')
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
        Schema::table('credit_note_details', function (Blueprint $table) {
            //
        });
    }
}
