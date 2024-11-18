<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateReturnViewsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('return_views', function (Blueprint $table) {
            $table->id();
            $table->string('MCU_CODE', 50);
            $table->string('MCU_NAME');
            $table->string('RTE_CODE', 50);
            $table->date('TranDate');
            $table->string('SMN_CODE', 50);
            $table->string('SMN_NAME');
            $table->string('ITM_CODE', 50);
            $table->string('ITM_NAME');
            $table->decimal('GoodReturn_CTN', 18, 2);
            $table->decimal('GoodReturn_PCS', 18, 2);
            $table->decimal('Damaged_PCS', 18, 2);
            $table->decimal('Expired_PCS', 18, 2);
            $table->decimal('NearExpiry_PCS', 18, 2);
            $table->enum('FLAG_GD_CTN', ['Y', 'N'])->default('N');
            $table->enum('FLAG_GD_PCS', ['Y', 'N'])->default('N');
            $table->enum('FLAG_DM', ['Y', 'N'])->default('N');
            $table->enum('FLAG_EX', ['Y', 'N'])->default('N');
            $table->enum('FLAG_NR', ['Y', 'N'])->default('N');
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
        Schema::dropIfExists('return_views');
    }
}
