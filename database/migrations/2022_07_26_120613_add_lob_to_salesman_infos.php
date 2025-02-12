<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddLobToSalesmanInfos extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('salesman_infos', function (Blueprint $table) {
            $table->boolean('is_lob')
                ->comment('1=LOB')
                ->default('0')
                ->after('current_stage_comment');

            $table->unsignedBigInteger('region_id')
                ->after('route_id')
                ->nullable();

            $table->unsignedBigInteger('salesman_helper_id')
                ->after('region_id')
                ->nullable()
                ->comment('Its comming from users table');

            $table->string('category_id', 50)
                ->after('salesman_role_id')
                ->nullable()
                ->comment('1: Salesman, 2: Salesman cum driver, 3: Helper, 4: Driver cum helper');

            $table->decimal('incentive', 8, 3)
                ->default('0')
                ->after('profile_image');

            $table->foreign('region_id')
                ->references('id')
                ->on('regions');

            $table->foreign('salesman_helper_id')->references('id')
                ->on('users')
                ->onDelete('cascade');
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
