<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCurrentStageToSalesmanUnloads extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('salesman_unloads', function (Blueprint $table) {
            $table->enum('current_stage', ['Pending', 'Approved', 'Rejected'])
                ->after('source')
                ->default('Pending');

            $table->text('current_stage_comment')->nullable()->after('current_stage');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('salesman_unloads', function (Blueprint $table) {
            //
        });
    }
}
