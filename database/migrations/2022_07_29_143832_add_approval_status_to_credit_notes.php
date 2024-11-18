<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddApprovalStatusToCreditNotes extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('credit_notes', function (Blueprint $table) {

            $table->unsignedBigInteger('route_id')
                ->after('delivery_driver_id')
                ->nullable();

            $table->decimal('pdc_amount', 18, 2)
                ->after('grand_total')
                ->default('0.00');

            $table->enum('approval_status', ['Created', 'Updated', 'Deleted', 'In-Process', 'Completed', 'Requested', 'Truck Allocated'])
                ->after('current_stage_comment')
                ->default('Created');

            $table->enum('order_type_id', ['1', '2'])
                ->after('credit_note_date')
                ->default('2');

            $table->enum('return_type', ['badReturn', 'goodReturn'])
                ->after('warehouse_id')
                ->default('badReturn');

            $table->boolean('is_exchange')
                ->after('status')
                ->default('0');

            $table->string('exchange_number', 50)
                ->after('is_exchange')
                ->nullable();

            $table->bigInteger('erp_id')
                ->after('exchange_number')
                ->nullable();

            $table->longText('erp_failed_response')
                ->after('erp_id')
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
        Schema::table('credit_notes', function (Blueprint $table) {
            //
        });
    }
}
