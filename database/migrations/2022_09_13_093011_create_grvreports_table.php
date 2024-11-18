<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGrvreportsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('grvreports', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid');
            $table->unsignedBigInteger('organisation_id');
            $table->date('tran_date');
            $table->unsignedBigInteger('ksm_id');
            $table->string('ksm_name');
            $table->string('reason');
            $table->integer('qty');
            $table->decimal('amount', 8, 2);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organisation_id')->references('id')
                ->on('organisations');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('grvreports');
    }
}
