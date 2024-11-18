<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddOriginalItemQtyAndOpenQtyAndInvoicedQtyToCreditNoteDetails extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('credit_note_details', function (Blueprint $table) {
            
            $table->decimal('invoiced_qty', 18, 2)->default('0.00')->comment('invoiced qyt')->after('batch_number');
            $table->decimal('open_qty', 18, 2)->default('0.00')->comment('credit note qty - invoiced_qty')->after('invoiced_qty');
            $table->decimal('original_item_qty',8, 2)->default('0.00')->after('open_qty');

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
