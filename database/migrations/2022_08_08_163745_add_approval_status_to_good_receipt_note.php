<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddApprovalStatusToGoodReceiptNote extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('good_receipt_note', function (Blueprint $table) {
            $table->unsignedBigInteger('salesman_id')
                ->after('organisation_id')
                ->nullable();

            $table->unsignedBigInteger('route_id')
                ->after('salesman_id')
                ->nullable();

            $table->enum('approval_status', ['Deleted', 'Created', 'Updated', 'Cancelled'])
                ->after('customer_refrence_number')
                ->default('Created');

            $table->foreign('salesman_id')->references('id')
                ->on('users')
                ->onDelete('cascade');

            $table->foreign('route_id')->references('id')
                ->on('routes')
                ->onDelete('cascade');
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
