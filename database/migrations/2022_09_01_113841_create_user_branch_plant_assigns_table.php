<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUserBranchPlantAssignsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('user_branch_plant_assigns', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid');

            $table->unsignedBigInteger('user_id')->comment('comes from users');
            $table->unsignedBigInteger('storage_location_id')->comment('comes from storagelocations');

            $table->foreign('user_id')->references('id')
                ->on('users');

            $table->foreign('storage_location_id')->references('id')
                ->on('storagelocations');


            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('user_branch_plant_assigns');
    }
}
