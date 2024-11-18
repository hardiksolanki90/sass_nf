<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddOriginalItemQtyToSalesmanUnloadDetails extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('salesman_unload_details', function (Blueprint $table) {
            $table->unsignedBigInteger('reason_id')
                ->after('salesman_unload_id')
                ->nullable();

            $table->decimal('original_item_qty')
                ->after('unload_qty')
                ->default(0);
        });

        Schema::table('good_receipt_note_detail', function (Blueprint $table) {
            $table->unsignedBigInteger('reason_id')
                ->after('good_receipt_note_id')
                ->nullable();

            $table->decimal('original_item_qty')
                ->after('qty')
                ->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('salesman_unload_details', function (Blueprint $table) {
            //
        });
    }
}
