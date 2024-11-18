<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateConsolidateLoadReturnReportsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('consolidate_load_return_reports', function (Blueprint $table) {
            $table->id();
            $table->string('SR_No')->nullable();
            $table->string('Item')->index()->nullable();
            $table->string('Item_description')->nullable();
            $table->string('qty')->index()->nullable();
            $table->string('uom')->index()->nullable();
            $table->string('sec_qty')->index()->nullable();
            $table->string('sec_uom')->index()->nullable();
            $table->string('from_location')->index()->nullable();
            $table->string('to_location')->index()->nullable();
            $table->string('from_lot_serial')->nullable();
            $table->string('to_lot_number')->nullable();
            $table->string('to_lot_status_code')->nullable();
            $table->string('load_date')->index()->nullable();
            $table->string('warehouse')->index()->nullable();
            $table->string('is_exported')->nullable();
            $table->string('salesman')->index()->nullable();
            $table->unsignedBigInteger('storage_location_id')->index()->nullable();
            $table->enum('type', ['unload', 'grv'])->default('unload');
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
        Schema::dropIfExists('consolidate_load_return_reports');
    }
}
