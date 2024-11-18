<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMoreFieldsToInvoices extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->unsignedBigInteger('route_id')
                ->after('van_id')
                ->nullable();

            $table->unsignedBigInteger('lob_id')
                ->after('route_id')
                ->nullable();

            $table->decimal('rounding_off_amount', 18, 2)
                ->after('grand_total')
                ->default('0.00');

            $table->decimal('pending_credit', 18, 2)
                ->after('rounding_off_amount')
                ->default('0.00');

            $table->decimal('pdc_amount', 18, 2)
                ->after('pending_credit')
                ->default('0.00');

            $table->enum('approval_status', ['Deleted', 'Created', 'Updated', 'In-Process', 'Completed'])
                ->after('current_stage_comment')
                ->default('Created');

            $table->boolean('is_exchange')
                ->after('payment_received')
                ->default('0');

            $table->string('exchange_number', 50)
                ->after('is_exchange')
                ->nullable();

            $table->boolean('is_premium_invoice')
                ->after('exchange_number')
                ->nullable();

            $table->string('customer_lpo', 100)
                ->after('is_premium_invoice')
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
        Schema::table('invoices', function (Blueprint $table) {
            //
        });
    }
}
