<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCashierRecieptTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cashier_reciept', function (Blueprint $table) {
            $table->id();
			$table->uuid('uuid');
			$table->string('cashier_reciept_number');
			$table->unsignedBigInteger('organisation_id');
			$table->unsignedBigInteger('route_id');
			$table->unsignedBigInteger('salesman_id');
			$table->date('date');
			$table->string('slip_number');
			$table->unsignedBigInteger('bank_id');
			$table->date('slip_date');
			$table->decimal('total_amount', 18,2)->default('0.00');
			$table->boolean('status')->default(0);
			$table->foreign('organisation_id')->references('id')->on('organisations')->onDelete('cascade');
			$table->foreign('route_id')->references('id')->on('routes')->onDelete('cascade');
            $table->timestamps();
			$table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('cashier_reciept');
    }
}
