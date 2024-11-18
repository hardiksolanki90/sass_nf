<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGeoApprovalsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('geo_approvals', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid');
            $table->unsignedBigInteger('organisation_id');
            $table->unsignedBigInteger('salesman_id')->nullable();
            $table->unsignedBigInteger('supervisor_id')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->string('salesman_lat')->nullable();
            $table->string('salesman_long')->nullable();
            $table->string('customer_lat')->nullable();
            $table->string('customer_long')->nullable();
            $table->string('radius')->nullable();
            $table->string('date')->nullable();
            $table->string('reason')->nullable();
            $table->enum('status', ['Approve', 'Reject', 'Pending'])
                ->default('Pending');
            $table->foreign('organisation_id')
                ->references('id')
                ->on('organisations')
                ->onDelete('cascade');
                
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
        Schema::dropIfExists('geo_approvals');
    }
}
