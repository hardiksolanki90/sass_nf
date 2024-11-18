<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddReasonIdToGoodReceiptNoteDetail extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('good_receipt_note_detail', function (Blueprint $table) {
            $table->unsignedBigInteger('reason_id')
                ->after('good_receipt_note_id')
                ->nullable();

            $table->foreign('reason_id')->references('id')
                ->on('reason_types');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('good_receipt_note_detail', function (Blueprint $table) {
            //
        });
    }
}
