<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPaidToDebitNotesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('debit_notes', function (Blueprint $table) {
            $table->unsignedBigInteger('lob_id')
                ->after('trip_id')
                ->nullable();

            $table->boolean('is_debit_note')
                ->after('status')
                ->default(1);

            $table->foreign('lob_id')->references('id')->on('lobs');
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
