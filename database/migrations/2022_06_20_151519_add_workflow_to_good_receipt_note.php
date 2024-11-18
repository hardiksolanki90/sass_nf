<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddWorkflowToGoodReceiptNote extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('good_receipt_note', function (Blueprint $table) {
            $table->enum('current_stage', ['Pending', 'Approved', 'Rejected'])
                ->default('Pending')
                ->after('status');
            $table->text('current_stage_comment')->nullable()
                ->after('current_stage');
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
