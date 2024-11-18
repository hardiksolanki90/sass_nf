<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSalesVsGrvsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('sales_vs_grvs', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->unsignedBigInteger('zone_id')->comment('comes from zones');
            $table->string('zone_name');
            $table->unsignedBigInteger('kam_id')->comment('users from zones');
            $table->string('kam_name')->comment('ITS NSM name');
            $table->decimal('invoice_qty', 8,2)->comment('comes from invoice');
            $table->decimal('invoice_amount', 8,2);
            $table->decimal('grv_qty', 8,2)->comment('comes from credit note');
            $table->decimal('grv_amount', 8,2);
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
        Schema::dropIfExists('sales_vs_grvs');
    }
}
