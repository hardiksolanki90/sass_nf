<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCustomerRefrenceNumberToGoodReceiptNote extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('good_receipt_note', function (Blueprint $table) {
            $table->string('customer_refrence_number')
                ->nullable()
                ->after('current_stage_comment');
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
