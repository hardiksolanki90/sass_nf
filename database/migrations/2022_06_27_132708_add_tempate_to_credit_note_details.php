<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTempateToCreditNoteDetails extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('credit_note_details', function (Blueprint $table) {
            $table->date('item_expiry_date')->nullable()
                ->after('reason');

            $table->unsignedBigInteger('invoice_id')
                ->after('item_expiry_date')
                ->nullable();

            $table->decimal('invoice_total')
                ->after('invoice_id')
                ->default('0.00');

            $table->unsignedBigInteger('template_order_id')
                ->nullable()
                ->after('invoice_total')
                ->comment('Its order id come from orders table');

            $table->unsignedBigInteger('template_sold_to_outlet_id')
                ->nullable()
                ->after('template_order_id')
                ->comment('Its customer_id come from users');

            $table->unsignedBigInteger('template_item_id')
                ->nullable()
                ->after('template_sold_to_outlet_id')
                ->comment('Its id come from items');

            $table->unsignedBigInteger('template_driver_id')
                ->nullable()
                ->after('template_item_id')
                ->comment('Its salesman_id come from users');

            $table->string('template_credit_note_number')
                ->nullable()
                ->after('template_driver_id');

            $table->string('template_sold_to_outlet_code')
                ->nullable()
                ->after('template_credit_note_number');

            $table->string('template_sold_to_outlet_name')
                ->nullable()
                ->after('template_sold_to_outlet_code');

            $table->date('template_return_request_date')
                ->nullable()
                ->after('template_sold_to_outlet_name')
                ->comment('Its delivery date');

            $table->string('template_item_name')
                ->nullable()
                ->after('template_return_request_date');

            $table->string('template_item_code')
                ->nullable()
                ->after('template_item_name');

            $table->decimal('template_total_value_in_case')
                ->default('0.00')
                ->after('template_item_code')
                ->comment('Its ctn(carton) value');

            $table->decimal('template_total_amount')
                ->default('0.00')
                ->after('template_total_value_in_case');

            $table->integer('template_delivery_sequnce')
                ->default('1')
                ->after('template_total_amount');

            $table->integer('template_trip')
                ->default('1')
                ->after('template_delivery_sequnce');

            $table->integer('template_trip_sequnce')
                ->default('1')
                ->after('template_trip');

            $table->string('template_vechicle', 50)
                ->nullable()
                ->after('template_trip_sequnce');

            $table->string('template_driver_name')
                ->nullable()
                ->after('template_vechicle')
                ->comment('its salesman name');

            $table->string('template_driver_code')
                ->nullable()
                ->after('template_driver_name')
                ->comment('its salesman code');

            $table->boolean('template_is_last_trip')
                ->default(0)
                ->after('template_driver_code')
                ->comment('check is it last trip of salesman.');
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
