<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSalesmanBlockToSalesmanInfos extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('salesman_infos', function (Blueprint $table) {
            $table->boolean('is_block')->default(0)->nullable()->after('profile_image');
            $table->date('block_start_date')->nullable()->after('is_block');
            $table->date('block_end_date')->nullable()->after('block_start_date');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('salesman_infos', function (Blueprint $table) {
            //
        });
    }
}
