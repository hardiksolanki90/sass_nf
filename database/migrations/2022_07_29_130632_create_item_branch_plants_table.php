<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateItemBranchPlantsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('item_branch_plants', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lob_id');
            $table->unsignedBigInteger('storage_location_id');
            $table->unsignedBigInteger('item_id');
            $table->boolean('status');

            $table->foreign('lob_id')->references('id')
                ->on('lobs')
                ->onDelete('cascade');

            $table->foreign('storage_location_id')->references('id')
                ->on('storagelocations')
                ->onDelete('cascade');

            $table->foreign('item_id')->references('id')
                ->on('items')
                ->onDelete('cascade');

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
        Schema::dropIfExists('item_branch_plants');
    }
}
