<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddRequestReasonToSalesmanRouteChangeApprovals extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('salesman_route_change_approvals', function (Blueprint $table) {
            $table->string('request_reason')
                ->after('reason')
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
        Schema::table('salesman_route_change_approvals', function (Blueprint $table) {
            //
        });
    }
}
