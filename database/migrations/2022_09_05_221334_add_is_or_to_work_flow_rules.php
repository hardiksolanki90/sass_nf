<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIsOrToWorkFlowRules extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('work_flow_rules', function (Blueprint $table) {
            $table->boolean('is_or')
                ->after('event_trigger')
                ->default(0)
                ->comment('Is or = 1; is and = 0');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('work_flow_rules', function (Blueprint $table) {
            //
        });
    }
}
