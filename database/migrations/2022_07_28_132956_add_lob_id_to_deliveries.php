<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddLobIdToDeliveries extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->unsignedBigInteger('warehouse_id')
                ->after('storage_location_id')
                ->nullable();

            $table->unsignedBigInteger('lob_id')
                ->after('warehouse_id')
                ->nullable();

            $table->enum('approval_status', ['Deleted', 'Created', 'Updated', 'In-Process', 'Partial-Invoiced', 'Completed', 'Cancel', 'Shipment', 'Truck Allocated', 'Picked'])
                ->after('current_stage_comment')
                ->default('Created');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('deliveries', function (Blueprint $table) {
            //
        });
    }
}
