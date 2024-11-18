<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddApprovalStatusToSalesmanLoads extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('salesman_loads', function (Blueprint $table) {
            $table->enum('approval_status', [
                'Deleted',
                'Created',
                'Updated',
                'In-Process',
                'Completed',
                'Cancel',
                'Shipment',
                'Truck Allocated',
                'Picked'
            ])->after('status')
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
        Schema::table('salesman_loads', function (Blueprint $table) {
            //
        });
    }
}
