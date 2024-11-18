<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddStatusToOrders extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->enum('order_status', ['created', 'partial', 'full'])
                ->default('created')
                ->after('sync_status');

            $table->enum('picking_status', ['partial', 'full'])
                ->nullable()
                ->after('order_status');

            $table->enum('transportation_status', ['No', 'Delegated'])
                ->nullable()
                ->after('picking_status');

            $table->enum('shipment_status', ['partial', 'full'])
                ->nullable()
                ->after('transportation_status');

            $table->enum('invoice_status', ['partial', 'full'])
                ->nullable()
                ->after('shipment_status');
        });

        Schema::table('order_details', function (Blueprint $table) {

            $table->enum('picking_status', ['partial', 'full'])
                ->nullable()
                ->after('order_status');

            $table->enum('transportation_status', ['No', 'Delegated'])
                ->nullable()
                ->after('picking_status');

            $table->enum('shipment_status', ['partial', 'full'])
                ->nullable()
                ->after('transportation_status');

            $table->enum('invoice_status', ['partial', 'full'])
                ->nullable()
                ->after('shipment_status');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('orders', function (Blueprint $table) {
            //
        });
    }
}
